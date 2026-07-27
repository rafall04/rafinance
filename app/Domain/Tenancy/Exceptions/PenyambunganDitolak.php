<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Exceptions;

use App\Domain\Tenancy\Enums\SocialProvider;
use RuntimeException;

/**
 * Penyambungan akun pihak ketiga yang ditolak.
 *
 * Pesannya ditulis untuk dibaca orang yang sedang bingung kenapa tombolnya
 * tidak bekerja — bukan untuk dibaca programmer. Setiap alasan menjelaskan apa
 * yang harus dilakukan berikutnya, karena orang yang gagal masuk tanpa tahu
 * jalan keluarnya akan berhenti mencoba.
 */
final class PenyambunganDitolak extends RuntimeException
{
    public static function tanpaSurel(SocialProvider $penyedia): self
    {
        return new self(sprintf(
            '%s tidak memberikan alamat surel Anda, padahal Rafin membutuhkannya sebagai jalan '
            .'pulang kalau akses ke perangkat hilang. Izinkan berbagi surel, atau daftar dengan surel biasa.',
            $penyedia->label(),
        ));
    }

    /**
     * Inilah penolakan yang mencegah pengambilalihan akun.
     *
     * Surelnya cocok dengan akun yang sudah ada, tapi penyedianya tidak menjamin
     * surel itu benar-benar milik orang yang sedang masuk. Menyambungkannya
     * otomatis berarti menyerahkan buku kas seseorang kepada siapa pun yang bisa
     * mendaftar di penyedia itu dengan surel tersebut.
     */
    public static function surelSudahDipakai(SocialProvider $penyedia): self
    {
        return new self(sprintf(
            'Surel ini sudah terdaftar di Rafin, dan %s tidak memastikan bahwa surel tersebut '
            .'benar-benar milik Anda. Masuk dulu dengan kata sandi, lalu sambungkan %s dari halaman Keamanan.',
            $penyedia->label(),
            $penyedia->label(),
        ));
    }

    public static function sudahDipakaiOrangLain(SocialProvider $penyedia): self
    {
        return new self(sprintf(
            'Akun %s ini sudah tersambung ke pengguna Rafin yang lain. Putuskan dulu dari akun itu, '
            .'atau pakai akun %s yang berbeda.',
            $penyedia->label(),
            $penyedia->label(),
        ));
    }

    public static function satuSatunyaCaraMasuk(SocialProvider $penyedia): self
    {
        return new self(sprintf(
            '%s adalah satu-satunya cara Anda masuk ke Rafin. Pasang kata sandi dulu, '
            .'atau sambungkan penyedia lain, sebelum memutuskannya.',
            $penyedia->label(),
        ));
    }

    public static function gagalDiPenyedia(SocialProvider $penyedia): self
    {
        return new self(sprintf(
            'Masuk lewat %s tidak selesai. Coba lagi, atau pakai surel dan kata sandi.',
            $penyedia->label(),
        ));
    }
}
