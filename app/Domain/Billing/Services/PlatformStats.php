<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use Illuminate\Support\Facades\DB;

/**
 * Angka untuk dasbor admin platform.
 *
 * Sengaja tinggal di domain Billing, BUKAN di app/Filament. Alasannya
 * struktural: arch test aturan A5 memindai app/Filament dan menolak setiap
 * rujukan ke model finansial, jadi widget dasbor tidak boleh menyentuh
 * Transaction sama sekali. Kelas ini yang menyentuhnya — dan ia hanya bisa
 * mengembalikan HITUNGAN.
 *
 * Setiap metode di sini mengembalikan int. Tidak ada satu pun yang
 * mengembalikan Money, dan itu bukan kebetulan: bentuk kembaliannya yang
 * membuat kebocoran nominal jadi mustahil, bukan disiplin pemanggilnya.
 */
final class PlatformStats
{
    public function jumlahPengguna(): int
    {
        return (int) DB::connection('pgsql')->table('users')->count();
    }

    public function jumlahWorkspace(): int
    {
        return (int) DB::connection('pgsql')->table('workspaces')->whereNull('deleted_at')->count();
    }

    /**
     * Workspace yang punya transaksi dalam 30 hari terakhir.
     */
    public function workspaceAktif(): int
    {
        return (int) DB::connection('pgsql')
            ->table('transactions')
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct()
            ->count('workspace_id');
    }

    /**
     * Hitungan transaksi. Tanpa nominal — memang tidak ada cara mendapatkannya
     * dari sini.
     */
    public function jumlahTransaksi(): int
    {
        return (int) DB::connection('pgsql')->table('transactions')->count();
    }

    public function transaksiBulanIni(): int
    {
        return (int) DB::connection('pgsql')
            ->table('transactions')
            ->where('booked_date', '>=', now()->startOfMonth()->toDateString())
            ->count();
    }

    /**
     * Persentase input yang gagal dibaca parser dalam 30 hari terakhir.
     *
     * Metrik produk yang paling jujur soal kualitas parser: kalau angkanya
     * naik, orang sedang mengetik dengan cara yang belum dimengerti mesin.
     */
    public function tingkatGagalParse(): int
    {
        $total = (int) DB::connection('pgsql')
            ->table('inbox_items')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($total === 0) {
            return 0;
        }

        $gagal = (int) DB::connection('pgsql')
            ->table('inbox_items')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('parse_status', 'failed')
            ->count();

        return (int) round($gagal / $total * 100);
    }

    public function jobGagal(): int
    {
        return (int) DB::connection('pgsql')->table('failed_jobs')->count();
    }

    public function antreanMenunggu(): int
    {
        return (int) DB::connection('pgsql')->table('jobs')->count();
    }

    public function webhookGagal(): int
    {
        return (int) DB::connection('pgsql')
            ->table('telegram_updates')
            ->where('status', 'failed')
            ->count();
    }

    public function izinDukunganBerlaku(): int
    {
        return (int) DB::connection('pgsql')
            ->table('support_access_grants')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();
    }
}
