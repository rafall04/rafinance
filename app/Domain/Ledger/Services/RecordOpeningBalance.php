<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Services;

use App\Domain\Ledger\DraftTransaction;
use App\Domain\Ledger\EntryLine;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Support\WaktuBuku;

/**
 * Mencatat saldo awal sebagai transaksi sungguhan.
 *
 * Kolom opening_balance_minor saja tidak cukup, dan ini bukan soal kerapian.
 * Saldo awal yang hanya berupa angka di kolom akun adalah harta tanpa asal:
 * neraca akan menunjukkan harta Rp 1.000.000 tanpa modal yang menyeimbangkan,
 * dan persamaan Harta = Utang + Modal langsung meleset sejak hari pertama.
 *
 * Yang dilakukan di sini adalah yang dilakukan pembukuan mana pun: mendebit
 * akunnya, mengkredit modal. Setelah itu saldo awal ikut terlihat di buku
 * besar, ikut terhitung di laporan, dan tidak lagi jadi angka yatim.
 */
final class RecordOpeningBalance
{
    public function __construct(
        private readonly PostTransaction $post,
    ) {}

    public function __invoke(Account $akun): ?Transaction
    {
        if ($akun->is_system || $akun->opening_balance_minor->isZero()) {
            return null;
        }

        $modal = Account::sistem(AccountType::Equity, $akun->currency);
        $nominal = $akun->opening_balance_minor;

        return ($this->post)(DraftTransaction::mentah(
            kind: TransactionKind::Opening,
            lines: [
                new EntryLine($akun->getKey(), $nominal->minor, 0),
                new EntryLine($modal->getKey(), -$nominal->minor, 1),
            ],
            description: 'Saldo awal '.$akun->name,
            // Tanggal hari ini, bukan tanggal jauh di masa lalu: saldo awal
            // adalah pernyataan tentang keadaan SEKARANG, dan menanggalkannya
            // mundur akan mengubah laporan periode yang sudah ditutup.
            //
            // "Hari ini" menurut zona waktu buku, bukan UTC — kalau tidak,
            // saldo awal yang dicatat pukul dua pagi WIB tertanggal kemarin.
            bookedDate: WaktuBuku::hariIni(),
        ));
    }
}
