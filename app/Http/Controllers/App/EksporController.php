<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor transaksi ke CSV.
 *
 * Ekspor diperlakukan sebagai peristiwa keamanan, bukan sebagai fitur biasa.
 * Alasannya sederhana: ini satu-satunya jalur yang mengeluarkan seluruh isi
 * buku dalam bentuk utuh. Seseorang yang berhasil masuk ke akun orang lain akan
 * memakai jalur ini, dan pemilik yang sah harus tahu bahwa itu terjadi — karena
 * itu pencatatan dan pemberitahuannya bukan pilihan.
 */
final class EksporController
{
    public function __invoke(
        Request $request,
        TenantContext $tenant,
        SecurityLogger $keamanan,
        AuditLogger $audit,
    ): StreamedResponse {
        $data = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ]);

        $workspace = $tenant->workspace();
        abort_if($workspace === null, 404);

        $dari = CarbonImmutable::parse($data['dari'] ?? CarbonImmutable::now()->startOfMonth()->toDateString());
        $sampai = CarbonImmutable::parse($data['sampai'] ?? CarbonImmutable::now()->toDateString());

        $transaksi = Transaction::query()
            ->where('status', '!=', TransactionStatus::Draft->value)
            ->whereBetween('booked_date', [$dari->toDateString(), $sampai->toDateString()])
            ->with(['entries.account', 'category', 'project', 'contact'])
            ->orderBy('booked_date')
            ->orderBy('created_at')
            ->get();

        // security_events: metadata saja, tanpa satu pun nominal (aturan A6).
        $keamanan->log(
            SecurityEventType::DataExported,
            user: $request->user(),
            request: $request,
            metadata: [
                'format' => 'csv',
                'row_count' => $transaksi->count(),
                'from' => $dari->toDateString(),
                'to' => $sampai->toDateString(),
            ],
        );

        // audit_logs: milik workspace, boleh memuat nominal.
        $audit->record(
            action: AuditAction::DataExported,
            after: [
                'format' => 'csv',
                'rows' => $transaksi->count(),
                'from' => $dari->toDateString(),
                'to' => $sampai->toDateString(),
            ],
        );

        $namaBerkas = sprintf(
            'rafin-%s-%s-%s.csv',
            preg_replace('/[^a-z0-9]+/i', '-', strtolower($workspace->name)) ?: 'buku',
            $dari->format('Ymd'),
            $sampai->format('Ymd'),
        );

        return response()->streamDownload(function () use ($transaksi): void {
            $keluaran = fopen('php://output', 'w');

            // BOM UTF-8: tanpa ini, Excel di Windows menampilkan nama kategori
            // berhuruf beraksen sebagai karakter rusak, dan pengguna akan
            // menyimpulkan datanya yang rusak.
            fwrite($keluaran, "\xEF\xBB\xBF");

            fputcsv($keluaran, [
                'Tanggal', 'Keterangan', 'Jenis', 'Kategori', 'Proyek', 'Kontak',
                'Akun', 'Nominal', 'Nominal (sen)', 'Status', 'Sumber', 'ID',
            ]);

            foreach ($transaksi as $satu) {
                $akunUang = $satu->entries
                    ->filter(fn ($entry): bool => ! ($entry->account?->is_system ?? false))
                    ->map(fn ($entry): string => (string) $entry->account?->name)
                    ->filter()
                    ->implode(' → ');

                $nominal = $satu->amount();

                fputcsv($keluaran, [
                    $satu->booked_date->toDateString(),
                    $satu->description ?? '',
                    $satu->kind->label(),
                    $satu->category?->fullName() ?? '',
                    $satu->project?->name ?? '',
                    $satu->contact?->name ?? '',
                    $akunUang,
                    $nominal->formatPlain(),
                    // Kolom sen disertakan supaya angkanya bisa dihitung ulang
                    // tanpa menebak format ribuan dan desimal.
                    $nominal->minor,
                    $satu->status->label(),
                    $satu->source->label(),
                    $satu->getKey(),
                ]);
            }

            fclose($keluaran);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
