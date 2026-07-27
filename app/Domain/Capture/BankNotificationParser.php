<?php

declare(strict_types=1);

namespace App\Domain\Capture;

use App\Domain\Ledger\Enums\TransactionKind;
use App\Support\Money;

/**
 * Membaca teks notifikasi m-banking dan e-wallet yang diteruskan ke bot.
 *
 * Ini jalur yang paling sering dipakai orang di lapangan, dan alasannya masuk
 * akal: nominalnya sudah pasti benar karena datang dari banknya sendiri, dan
 * tidak ada kesempatan salah ketik. Karena itu kualitasnya diprioritaskan —
 * satu pola per penyedia, ditulis dari bentuk pesan yang sungguhan.
 *
 * Pola ditulis longgar dengan sengaja. Bank mengubah kalimat notifikasinya
 * tanpa memberi tahu siapa pun, dan pola yang menuntut kecocokan persis akan
 * berhenti bekerja di hari yang tidak bisa diprediksi. Yang gagal dibaca tetap
 * masuk inbox, bukan hilang.
 */
final class BankNotificationParser
{
    /**
     * @var array<string, array{penyedia: string, arah: TransactionKind}>
     */
    private const POLA = [
        // BCA: "Transfer Rp 500.000 ke BUDI berhasil"
        '/\bbca\b|\bm-?bca\b|\bklikbca\b/iu' => ['penyedia' => 'BCA', 'arah' => TransactionKind::Expense],
        '/\bmandiri\b|\blivin\b/iu' => ['penyedia' => 'Mandiri', 'arah' => TransactionKind::Expense],
        '/\bbri\b|\bbrimo\b/iu' => ['penyedia' => 'BRI', 'arah' => TransactionKind::Expense],
        '/\bbni\b|\bwondr\b/iu' => ['penyedia' => 'BNI', 'arah' => TransactionKind::Expense],
        '/\bgopay\b|\bgojek\b/iu' => ['penyedia' => 'GoPay', 'arah' => TransactionKind::Expense],
        '/\bovo\b/iu' => ['penyedia' => 'OVO', 'arah' => TransactionKind::Expense],
        '/\bdana\b/iu' => ['penyedia' => 'DANA', 'arah' => TransactionKind::Expense],
        '/\bshopeepay\b/iu' => ['penyedia' => 'ShopeePay', 'arah' => TransactionKind::Expense],
        '/\bseabank\b/iu' => ['penyedia' => 'SeaBank', 'arah' => TransactionKind::Expense],
        '/\bjago\b/iu' => ['penyedia' => 'Jago', 'arah' => TransactionKind::Expense],
    ];

    /**
     * Kata yang menandakan uang MASUK, meski notifikasinya dari bank yang sama.
     *
     * @var array<int, string>
     */
    private const PENANDA_MASUK = [
        'diterima', 'terima', 'masuk', 'kredit', 'penerimaan', 'top up berhasil',
        'topup berhasil', 'refund', 'pengembalian', 'cashback', 'bonus',
    ];

    /**
     * @var array<int, string>
     */
    private const PENANDA_KELUAR = [
        'transfer', 'pembayaran', 'bayar', 'pembelian', 'beli', 'tarik tunai',
        'debit', 'penarikan', 'qris', 'berhasil dibayar',
    ];

    /**
     * @return array{penyedia: string, arah: TransactionKind, nominal: ?Money, pihak: ?string}|null
     */
    public function __invoke(string $teks, string $currency = 'IDR'): ?array
    {
        $penyedia = $this->penyedia($teks);

        if ($penyedia === null) {
            return null;
        }

        $nominal = $this->nominal($teks, $currency);

        if ($nominal === null) {
            return null;
        }

        return [
            'penyedia' => $penyedia,
            'arah' => $this->arah($teks),
            'nominal' => $nominal,
            'pihak' => $this->pihak($teks),
        ];
    }

    public function sepertinyaNotifikasiBank(string $teks): bool
    {
        return $this->penyedia($teks) !== null
            && preg_match('/\brp\.?\s*[0-9]/iu', $teks) === 1;
    }

    private function penyedia(string $teks): ?string
    {
        foreach (self::POLA as $pola => $info) {
            if (preg_match($pola, $teks) === 1) {
                return $info['penyedia'];
            }
        }

        return null;
    }

    private function arah(string $teks): TransactionKind
    {
        $rendah = mb_strtolower($teks);

        foreach (self::PENANDA_MASUK as $kata) {
            if (str_contains($rendah, $kata)) {
                return TransactionKind::Income;
            }
        }

        foreach (self::PENANDA_KELUAR as $kata) {
            if (str_contains($rendah, $kata)) {
                return TransactionKind::Expense;
            }
        }

        // Notifikasi bank paling sering soal uang keluar.
        return TransactionKind::Expense;
    }

    /**
     * Nominal terbesar yang didahului "Rp".
     *
     * Terbesar, bukan yang pertama: notifikasi sering memuat saldo akhir di
     * baris berikutnya, dan yang pertama muncul belum tentu nominal
     * transaksinya. Saldo hampir selalu lebih besar — jadi diambil yang
     * TERKECIL kalau ada dua, karena saldo akhir yang besar bukan nilai
     * transaksinya.
     */
    private function nominal(string $teks, string $currency): ?Money
    {
        if (preg_match_all('/\brp\.?\s*([0-9][0-9.,]*)/iu', $teks, $cocok) === false) {
            return null;
        }

        $kandidat = [];

        foreach ($cocok[1] ?? [] as $angka) {
            try {
                $uang = Money::parse($angka, $currency);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (! $uang->isZero()) {
                $kandidat[] = $uang;
            }
        }

        if ($kandidat === []) {
            return null;
        }

        usort($kandidat, fn (Money $a, Money $b): int => $a->compareTo($b));

        return $kandidat[0];
    }

    /**
     * Nama penerima atau pengirim, kalau notifikasinya menyebutnya.
     */
    private function pihak(string $teks): ?string
    {
        $pola = [
            '/\bke\s+([A-Z][A-Za-z\s]{2,30}?)(?=\s+(?:berhasil|sebesar|senilai|rp|pada|$))/u',
            '/\bdari\s+([A-Z][A-Za-z\s]{2,30}?)(?=\s+(?:berhasil|sebesar|senilai|rp|pada|$))/u',
            '/\bkepada\s+([A-Z][A-Za-z\s]{2,30}?)(?=\s+(?:berhasil|sebesar|senilai|rp|pada|$))/u',
        ];

        foreach ($pola as $satu) {
            if (preg_match($satu, $teks, $cocok) === 1) {
                $nama = trim($cocok[1]);

                if ($nama !== '') {
                    return $nama;
                }
            }
        }

        return null;
    }
}
