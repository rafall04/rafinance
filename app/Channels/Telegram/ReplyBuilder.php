<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Domain\Capture\NormalizedDraft;
use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Menyusun balasan bot.
 *
 * Balasan selalu memuat saldo sesudahnya. Itu bukan hiasan: konfirmasi tanpa
 * saldo hanya memberi tahu bahwa sesuatu tersimpan, sementara konfirmasi dengan
 * saldo menjawab pertanyaan yang sebenarnya ada di kepala orang saat mencatat —
 * "sisa berapa".
 */
final class ReplyBuilder
{
    public function tersimpan(Transaction $transaksi, Account $akun): string
    {
        $transaksi->loadMissing(['entries', 'category', 'project']);

        $rincian = array_filter([
            $transaksi->description,
            $transaksi->category?->fullName(),
            $akun->name,
            $transaksi->project?->name,
        ]);

        return implode("\n", array_filter([
            sprintf('✓ %s %s', $transaksi->kind->label(), $transaksi->amount()->format()),
            $rincian !== [] ? '   '.$this->escape(implode(' · ', $rincian)) : null,
            sprintf('   Saldo %s: %s', $this->escape($akun->name), $akun->fresh()->balance()->format()),
        ]));
    }

    public function masukInbox(NormalizedDraft $draft, string $alasan): string
    {
        return implode("\n", array_filter([
            '📥 Tersimpan di inbox, lengkapi nanti.',
            '   <i>'.$this->escape(mb_substr($draft->rawText, 0, 120)).'</i>',
            '   '.$this->escape($alasan),
        ]));
    }

    /**
     * @param  Collection<int, Account>  $akun
     */
    public function saldo(iterable $akun, Money $total): string
    {
        $baris = ['<b>Saldo</b>'];

        foreach ($akun as $satu) {
            $baris[] = sprintf('   %s: %s', $this->escape($satu->name), $satu->balance()->format());
        }

        $baris[] = '';
        $baris[] = sprintf('<b>Total: %s</b>', $total->format());

        return implode("\n", $baris);
    }

    public function ringkasanPeriode(string $judul, Money $masuk, Money $keluar, int $jumlah): string
    {
        return implode("\n", [
            '<b>'.$this->escape($judul).'</b>',
            sprintf('   Masuk:  %s', $masuk->format()),
            sprintf('   Keluar: %s', $keluar->format()),
            sprintf('   Selisih: %s', $masuk->minus($keluar)->format()),
            '',
            sprintf('   %d transaksi', $jumlah),
        ]);
    }

    public function bantuan(): string
    {
        return implode("\n", [
            '<b>Cara pakai</b>',
            '',
            'Ketik saja apa yang terjadi:',
            '   <code>50k bensin</code>',
            '   <code>50rb bensin bca</code>',
            '   <code>+2jt dp event pak budi</code>',
            '   <code>150.000 solar genset #eventA</code>',
            '   <code>pindah 500k kas ke bca</code>',
            '',
            '<b>Perintah</b>',
            '   /saldo — saldo semua akun',
            '   /hariini — ringkasan hari ini',
            '   /bulanini — ringkasan bulan ini',
            '   /inbox — yang belum lengkap',
            '   /undo — batalkan catatan terakhir',
            '   /laporan — ringkasan periode',
            '   /switch — ganti buku',
            '   /bantuan — pesan ini',
        ]);
    }

    public function belumTerhubung(): string
    {
        return implode("\n", [
            'Akun Telegram ini belum terhubung ke Rafin.',
            '',
            'Buka Rafin di web → Pengaturan → Telegram, lalu kirim kode enam digitnya ke sini:',
            '   <code>/link 123456</code>',
        ]);
    }

    /**
     * Telegram memakai HTML terbatas; tanda kurung sudut harus dilolosi supaya
     * keterangan yang memuatnya tidak merusak seluruh pesan.
     */
    private function escape(string $teks): string
    {
        return htmlspecialchars($teks, ENT_NOQUOTES, 'UTF-8');
    }
}
