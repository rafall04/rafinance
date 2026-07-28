<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Tanggal buku, menurut zona waktu tempat bukunya berada.
 *
 * Rafin menjalankan APP_TIMEZONE=UTC dengan sengaja: seluruh timestamp
 * disimpan dalam UTC, dan itu memang cara yang benar untuk `created_at`.
 * Tapi `booked_date` bukan timestamp — ia jawaban atas pertanyaan "ini
 * pengeluaran tanggal berapa?", dan yang berhak menjawabnya adalah kalender
 * di dinding pemiliknya, bukan Greenwich.
 *
 * Selisihnya bukan hal teoretis. Bagi pengguna WIB (UTC+7), setiap catatan
 * yang dibuat antara tengah malam dan pukul tujuh pagi jatuh ke tanggal
 * KEMARIN kalau dihitung dengan now() polos. Di pergantian bulan ia jatuh ke
 * BULAN yang salah — dan aturan A10 menyatakan seluruh laporan dan anggaran
 * memakai booked_date. Untuk pengguna WIT (UTC+9) jendelanya sembilan jam.
 *
 * Itulah yang terjadi pada kanal Telegram sampai Juli 2026: parser-nya tidak
 * pernah menghasilkan tanggal, jadi setiap transaksi memakai now() UTC.
 *
 * Zona waktunya diambil dari workspace, bukan dari pengguna: satu buku yang
 * dikerjakan dua orang di dua zona waktu tetap harus punya satu tanggal tutup
 * yang sama, kalau tidak laporannya tidak pernah bisa dijumlahkan.
 */
final class WaktuBuku
{
    /**
     * Saat ini, menurut zona waktu buku yang sedang aktif.
     */
    public static function sekarang(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }

    /**
     * Tanggal hari ini di buku yang sedang aktif, sebagai "YYYY-MM-DD".
     */
    public static function hariIni(): string
    {
        return self::sekarang()->toDateString();
    }

    /**
     * Zona waktu buku yang sedang aktif.
     *
     * Jatuh ke bawaan konfigurasi kalau tidak ada konteks tenant — jalur
     * seperti perintah artisan dan test yang menulis sebelum workspace-nya
     * terpasang. Yang penting ia tidak pernah diam-diam menjadi UTC.
     */
    public static function zona(): string
    {
        $zona = app(TenantContext::class)->workspace()?->timezone;

        return is_string($zona) && $zona !== ''
            ? $zona
            : (string) config('rafin.default_timezone', 'Asia/Jakarta');
    }
}
