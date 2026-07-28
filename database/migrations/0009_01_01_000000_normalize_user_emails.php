<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Surel sebagai identitas yang tidak peka huruf besar-kecil.
 *
 * PostgreSQL membandingkan varchar secara peka huruf, jadi index unik biasa
 * pada `email` menganggap "Budi@Gmail.com" dan "budi@gmail.com" sebagai dua
 * nilai berbeda. Bagi penyedia surel mana pun keduanya kotak yang sama, dan
 * dua akun untuk satu kotak berarti dua buku kas terpisah yang pemiliknya
 * tidak pernah tahu keberadaan salah satunya.
 *
 * Fortify memang sudah menormalkan di pintu depan, dan model User sekarang
 * ikut menormalkan. Migration ini yang membuat aturannya tidak bisa dilanggar
 * oleh jalur mana pun — termasuk psql langsung.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        // 1. Rapikan yang sudah ada. Dilakukan sebelum index dipasang, kalau
        //    tidak pemasangannya akan gagal justru pada data yang mau
        //    diperbaiki.
        $connection->statement('UPDATE users SET email = lower(btrim(email)) WHERE email <> lower(btrim(email))');

        // 2. Kalau normalisasi di atas memunculkan tabrakan, ia sudah gagal
        //    lebih dulu di index unik lama — dan itu memang yang kita mau:
        //    dua akun untuk satu kotak surel adalah keadaan yang harus
        //    diselesaikan manusia, bukan ditebak migration. Yang dipilih
        //    otomatis pasti salah bagi salah satu pemiliknya.

        // 3. Index fungsional. Ini yang menegakkannya di tingkat database,
        //    apa pun yang dilakukan kode di atasnya.
        $connection->statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
