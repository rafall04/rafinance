<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation\Services;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\EntryLine;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Reconciliation\Models\Reconciliation;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Cash opname: menghitung uang sungguhan, lalu merapikan buku agar cocok.
 *
 * Selisihnya tidak pernah dihapus diam-diam. Ia dicatat sebagai transaksi
 * penyesuaian dengan lawan di akun beban atau pendapatan, sehingga muncul di
 * laporan sebagaimana mestinya — orang berhak tahu bahwa bulan ini ada
 * Rp 35.000 yang tidak bisa dijelaskan, karena angka itulah yang memberi tahu
 * ada sesuatu yang perlu diperiksa.
 */
final class PerformCashCount
{
    public function __construct(
        private readonly PostTransaction $post,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(
        Account $akun,
        Money $terhitung,
        ?string $tanggal = null,
        ?string $catatan = null,
    ): Reconciliation {
        $tanggal ??= now()->toDateString();
        $sistem = $akun->balance();
        $selisih = $terhitung->minus($sistem);

        return DB::connection('pgsql')->transaction(function () use ($akun, $terhitung, $sistem, $selisih, $tanggal, $catatan): Reconciliation {
            $penyesuaian = $selisih->isZero()
                ? null
                : $this->buatPenyesuaian($akun, $selisih, $tanggal);

            $hasil = Reconciliation::query()->create([
                'account_id' => $akun->getKey(),
                'as_of_date' => $tanggal,
                'system_balance_minor' => $sistem,
                'counted_balance_minor' => $terhitung,
                'difference_minor' => $selisih,
                'adjustment_transaction_id' => $penyesuaian?->getKey(),
                'performed_by' => auth()->id(),
                'note' => $catatan,
            ]);

            $this->audit->record(
                action: AuditAction::ReconciliationPerformed,
                auditable: $hasil,
                after: [
                    'account_id' => $akun->getKey(),
                    'system_minor' => $sistem->minor,
                    'counted_minor' => $terhitung->minor,
                    'difference_minor' => $selisih->minor,
                ],
            );

            return $hasil;
        });
    }

    /**
     * Transaksi penyesuaian.
     *
     * Uang yang lebih diperlakukan sebagai pendapatan tak terduga; yang kurang
     * sebagai beban. Keduanya masuk ke akun sistem, bukan disembunyikan ke
     * modal — supaya terlihat di laba rugi dan bisa ditanyakan.
     */
    private function buatPenyesuaian(Account $akun, Money $selisih, string $tanggal)
    {
        $lawan = Account::sistem(
            $selisih->isPositive() ? AccountType::Income : AccountType::Expense,
            $akun->currency,
        );

        return ($this->post)(DraftTransaction::mentah(
            kind: TransactionKind::Adjustment,
            lines: [
                new EntryLine($akun->getKey(), $selisih->minor, 0),
                new EntryLine($lawan->getKey(), -$selisih->minor, 1),
            ],
            bookedDate: $tanggal,
            description: sprintf(
                'Penyesuaian cash opname %s (%s)',
                $akun->name,
                $selisih->isPositive() ? 'lebih' : 'kurang',
            ),
        ));
    }
}
