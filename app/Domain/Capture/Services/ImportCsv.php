<?php

declare(strict_types=1);

namespace App\Domain\Capture\Services;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Services\PostTransaction;
use App\Domain\Logging\AuditLogger;
use App\Domain\Logging\Enums\AuditAction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Impor transaksi dari CSV.
 *
 * Dua keputusan yang membentuk seluruh perilakunya:
 *
 * 1. **Baris yang gagal tidak menghentikan yang lain.** Berkas ekspor bank
 *    hampir selalu punya beberapa baris aneh — header ganda, baris saldo,
 *    keterangan kosong. Impor yang berhenti di baris ke-40 dari 300 memaksa
 *    orang membersihkan berkasnya secara manual, dan sebagian besar menyerah.
 *
 * 2. **Setiap baris tetap melewati PostTransaction.** Tidak ada penulisan
 *    massal langsung ke database: trigger keseimbangan, penguncian periode, dan
 *    jejak audit berlaku sama persis seperti kalau diketik satu per satu.
 */
final class ImportCsv
{
    public function __construct(
        private readonly PostTransaction $post,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{berhasil: int, gagal: array<int, array{baris: int, alasan: string}>}
     */
    public function __invoke(string $isiCsv, Account $akunBawaan, string $currency = 'IDR'): array
    {
        $baris = preg_split('/\r\n|\r|\n/', trim($isiCsv)) ?: [];

        if ($baris === []) {
            return ['berhasil' => 0, 'gagal' => []];
        }

        $kepala = $this->petakanKepala((string) array_shift($baris));
        $berhasil = 0;
        $gagal = [];

        foreach ($baris as $nomor => $satu) {
            $nomorBaris = $nomor + 2; // +1 karena header, +1 karena manusia menghitung dari satu

            if (trim($satu) === '') {
                continue;
            }

            try {
                $this->imporBaris($this->uraikan($satu), $kepala, $akunBawaan, $currency);
                $berhasil++;
            } catch (Throwable $galat) {
                $gagal[] = ['baris' => $nomorBaris, 'alasan' => $galat->getMessage()];
            }
        }

        if ($berhasil > 0) {
            $this->audit->record(
                action: AuditAction::DataImported,
                after: ['rows' => $berhasil, 'failed' => count($gagal), 'account_id' => $akunBawaan->getKey()],
            );
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal];
    }

    /**
     * @param  array<int, string>  $kolom
     * @param  array<string, int>  $kepala
     */
    private function imporBaris(array $kolom, array $kepala, Account $akun, string $currency): void
    {
        $ambil = fn (string $nama): ?string => isset($kepala[$nama]) ? ($kolom[$kepala[$nama]] ?? null) : null;

        $tanggal = $ambil('tanggal') ?? $ambil('date');
        $nominalMentah = $ambil('nominal') ?? $ambil('jumlah') ?? $ambil('amount');

        if ($tanggal === null || $nominalMentah === null || trim($nominalMentah) === '') {
            throw new \RuntimeException('Kolom tanggal atau nominal kosong.');
        }

        $nominal = Money::parse($nominalMentah, $currency);

        if ($nominal->isZero()) {
            throw new \RuntimeException('Nominal bernilai nol.');
        }

        // Arah ditentukan tanda nominal kalau kolom jenisnya tidak ada. Ekspor
        // bank umumnya memakai negatif untuk uang keluar.
        $jenis = mb_strtolower(trim((string) ($ambil('jenis') ?? $ambil('kind') ?? '')));

        $kind = match (true) {
            in_array($jenis, ['masuk', 'income', 'kredit', 'credit'], true) => TransactionKind::Income,
            in_array($jenis, ['keluar', 'expense', 'debit'], true) => TransactionKind::Expense,
            default => $nominal->isNegative() ? TransactionKind::Expense : TransactionKind::Income,
        };

        $keterangan = $ambil('keterangan') ?? $ambil('description') ?? null;
        $namaKategori = $ambil('kategori') ?? $ambil('category') ?? null;

        $kategori = $namaKategori === null || trim($namaKategori) === ''
            ? null
            : Category::query()->whereRaw('lower(name) = lower(?)', [trim($namaKategori)])->first();

        $draft = $kind === TransactionKind::Income
            ? DraftTransaction::pemasukan(
                amount: $nominal->abs(),
                to: $akun,
                bookedDate: CarbonImmutable::parse(trim($tanggal))->toDateString(),
                description: $keterangan,
                categoryId: $kategori?->getKey(),
                source: TransactionSource::Import,
            )
            : DraftTransaction::pengeluaran(
                amount: $nominal->abs(),
                from: $akun,
                bookedDate: CarbonImmutable::parse(trim($tanggal))->toDateString(),
                description: $keterangan,
                categoryId: $kategori?->getKey(),
                source: TransactionSource::Import,
            );

        ($this->post)($draft);
    }

    /**
     * @return array<string, int>
     */
    private function petakanKepala(string $baris): array
    {
        $kepala = [];

        foreach ($this->uraikan($baris) as $indeks => $nama) {
            $bersih = mb_strtolower(trim($nama, " \t\"'\u{FEFF}"));

            if ($bersih !== '') {
                $kepala[$bersih] = $indeks;
            }
        }

        return $kepala;
    }

    /**
     * Pemisah dideteksi otomatis: Excel berbahasa Indonesia mengekspor dengan
     * titik koma, bukan koma.
     *
     * @return array<int, string>
     */
    private function uraikan(string $baris): array
    {
        $pemisah = substr_count($baris, ';') > substr_count($baris, ',') ? ';' : ',';

        return array_map(
            static fn (string $sel): string => trim($sel, " \t\"'\u{FEFF}"),
            str_getcsv($baris, $pemisah, '"', '\\'),
        );
    }
}
