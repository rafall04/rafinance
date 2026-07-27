<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Services;

use App\Domain\Budgeting\Models\RecurringRule;
use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Tenancy\TenantContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Menjalankan aturan berulang yang sudah jatuh tempo.
 *
 * Satu aturan yang gagal tidak menghentikan sisanya. Aturan berulang berjalan
 * tanpa ditunggui siapa pun, dan job yang berhenti di aturan pertama yang
 * bermasalah akan membuat seluruh sewa dan gaji bulan itu tidak tercatat —
 * kegagalan yang jauh lebih besar daripada satu aturan yang rusak.
 */
final class RunRecurringRules
{
    public function __construct(
        private readonly PostTransaction $post,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return array{dijalankan: int, gagal: int}
     */
    public function __invoke(?CarbonImmutable $saat = null): array
    {
        $saat ??= CarbonImmutable::now();
        $dijalankan = 0;
        $gagal = 0;

        // Aturan dipindai lewat koneksi pemilik skema, yang ber-BYPASSRLS:
        // perintah ini berjalan dari penjadwal tanpa konteks tenant, dan tanpa
        // BYPASSRLS ia tidak akan melihat satu baris pun. Setiap aturan lalu
        // dijalankan DI DALAM konteks workspace-nya sendiri, sehingga penulisan
        // tetap tunduk pada RLS seperti biasa.
        //
        // Koneksinya bisa dikonfigurasi karena di dalam test seluruh data hidup
        // di transaksi yang belum di-commit, dan koneksi kedua tidak bisa
        // melihatnya.
        $aturan = RecurringRule::on((string) config('rafin.recurring_scan_connection', 'pgsql_migrate'))
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $saat)
            ->get();

        foreach ($aturan as $satu) {
            try {
                $this->tenant->runFor((string) $satu->workspace_id, function () use ($satu, $saat): void {
                    $this->jalankan($satu, $saat);
                });

                $dijalankan++;
            } catch (Throwable $galat) {
                $gagal++;

                Log::warning('Aturan berulang gagal', [
                    'rule_id' => $satu->getKey(),
                    'exception' => $galat->getMessage(),
                ]);
            }
        }

        return ['dijalankan' => $dijalankan, 'gagal' => $gagal];
    }

    private function jalankan(RecurringRule $aturan, CarbonImmutable $saat): void
    {
        $template = $aturan->template;

        $akun = Account::query()->find($template['account_id'] ?? null);

        if ($akun === null) {
            throw new \RuntimeException('Akun pada aturan berulang tidak ditemukan.');
        }

        $nominal = Money::ofMinor((int) ($template['amount_minor'] ?? 0), $akun->currency);

        if ($nominal->isZero()) {
            throw new \RuntimeException('Nominal aturan berulang bernilai nol.');
        }

        $kind = TransactionKind::tryFrom((string) ($template['kind'] ?? 'expense')) ?? TransactionKind::Expense;

        $draft = $kind === TransactionKind::Income
            ? DraftTransaction::pemasukan(
                amount: $nominal,
                to: $akun,
                bookedDate: $saat->toDateString(),
                description: $aturan->label,
                categoryId: $template['category_id'] ?? null,
                source: TransactionSource::Recurring,
            )
            : DraftTransaction::pengeluaran(
                amount: $nominal,
                from: $akun,
                bookedDate: $saat->toDateString(),
                description: $aturan->label,
                categoryId: $template['category_id'] ?? null,
                source: TransactionSource::Recurring,
            );

        ($this->post)($draft);

        // Dihitung dari jadwal seharusnya, bukan dari saat ini: kalau job
        // terlambat sehari, jadwal berikutnya tidak boleh ikut bergeser.
        $acuan = $aturan->next_run_at ?? $saat;

        $aturan->forceFill([
            'last_run_at' => $saat,
            'next_run_at' => $aturan->hitungBerikutnya(CarbonImmutable::instance($acuan)),
        ])->save();
    }
}
