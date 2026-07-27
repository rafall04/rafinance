<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Ledger\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Tombol inline.
 *
 * callback_data dibatasi 64 byte oleh Telegram, jadi isinya hanya kode aksi
 * dan ULID — tidak pernah nominal maupun keterangan.
 */
final class KeyboardBuilder
{
    /**
     * Tombol yang menyertai konfirmasi transaksi.
     *
     * @return array<int, array<int, array<string, string>>>
     */
    public function setelahTersimpan(Transaction $transaksi): array
    {
        $id = (string) $transaksi->getKey();

        return [
            [
                ['text' => 'Ubah kategori', 'callback_data' => "kat:{$id}"],
                ['text' => 'Ubah akun', 'callback_data' => "akn:{$id}"],
            ],
            [
                ['text' => 'Batalkan', 'callback_data' => "btl:{$id}"],
            ],
        ];
    }

    /**
     * @param  Collection<int, Category>  $kategori
     * @return array<int, array<int, array<string, string>>>
     */
    public function pilihKategori(Transaction $transaksi, Collection $kategori): array
    {
        $id = (string) $transaksi->getKey();

        // Dua kolom: nama kategori Indonesia sering panjang, dan tiga kolom
        // membuatnya terpotong di layar sempit.
        $tombol = $kategori
            ->take(10)
            ->map(fn (Category $satu): array => [
                'text' => $satu->name,
                'callback_data' => "sk:{$id}:".$satu->getKey(),
            ])
            ->chunk(2)
            ->map(fn (Collection $baris): array => $baris->values()->all())
            ->values()
            ->all();

        $tombol[] = [['text' => '← Batal', 'callback_data' => "nop:{$id}"]];

        return $tombol;
    }

    /**
     * @param  Collection<int, Account>  $akun
     * @return array<int, array<int, array<string, string>>>
     */
    public function pilihAkun(Transaction $transaksi, Collection $akun): array
    {
        $id = (string) $transaksi->getKey();

        $tombol = $akun
            ->take(10)
            ->map(fn (Account $satu): array => [
                'text' => $satu->name,
                'callback_data' => "sa:{$id}:".$satu->getKey(),
            ])
            ->chunk(2)
            ->map(fn (Collection $baris): array => $baris->values()->all())
            ->values()
            ->all();

        $tombol[] = [['text' => '← Batal', 'callback_data' => "nop:{$id}"]];

        return $tombol;
    }

    /**
     * @return array<int, array<int, array<string, string>>>
     */
    public function bukaDiWeb(string $url, string $label = 'Buka di Rafin'): array
    {
        return [[['text' => $label, 'url' => $url]]];
    }
}
