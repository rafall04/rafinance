<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

/**
 * Penyedia masuk pihak ketiga.
 *
 * Daftar ini sengaja pendek dan dipilih untuk pasar yang dituju. GitHub dan
 * sejenisnya tidak ada di sini bukan karena sulit, tapi karena pemilik warung
 * dan operator RT-RW net tidak punya akunnya — tombol yang tidak pernah ditekan
 * hanya menambah keraguan di layar yang seharusnya menenangkan.
 *
 * `verifiesEmail()` adalah bagian terpenting dari enum ini. Ia menentukan
 * apakah sebuah akun boleh disambungkan otomatis ke akun Rafin yang sudah ada
 * dengan surel yang sama. Penyedia yang tidak memverifikasi surel bisa dipakai
 * mendaftar atas nama orang lain — dan untuk aplikasi keuangan, itu jalan masuk
 * yang tidak boleh dibuka.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';
    case Facebook = 'facebook';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Apple => 'Apple',
            self::Facebook => 'Facebook',
        };
    }

    /**
     * Apakah penyedia ini menjamin surel yang dikirimnya sudah terverifikasi.
     *
     * Google dan Apple memverifikasi surel sebelum akun bisa dipakai. Facebook
     * TIDAK selalu — akun bisa dibuat dengan nomor telepon, dan surel yang
     * dikembalikan tidak selalu terbukti milik orang yang sama.
     *
     * Konsekuensinya nyata: masuk lewat Facebook tidak akan pernah otomatis
     * tersambung ke akun Rafin yang sudah ada. Pemiliknya harus masuk dengan
     * kata sandi dulu, lalu menyambungkannya sendiri dari halaman Keamanan.
     */
    public function verifiesEmail(): bool
    {
        return match ($this) {
            self::Google, self::Apple => true,
            self::Facebook => false,
        };
    }

    /**
     * Penyedia hanya muncul di layar kalau kredensialnya benar-benar diisi.
     *
     * Tombol yang membawa ke halaman galat penyedia lebih buruk daripada tombol
     * yang tidak ada sama sekali.
     */
    public function isConfigured(): bool
    {
        $config = config('services.'.$this->value);

        return is_array($config)
            && filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    /**
     * Penyedia yang siap dipakai, untuk dirender di halaman masuk dan daftar.
     *
     * @return array<int, self>
     */
    public static function tersedia(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $penyedia): bool => $penyedia->isConfigured(),
        ));
    }

    /**
     * Warna merek, dipakai sebagai aksen tipis pada tombol.
     */
    public function color(): string
    {
        return match ($this) {
            self::Google => '#4285F4',
            self::Apple => '#000000',
            self::Facebook => '#1877F2',
        };
    }
}
