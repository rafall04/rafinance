<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Metadata mata uang.
 *
 * Dua angka yang gampang tertukar dan sengaja dipisah di sini:
 *
 *   minorUnit      berapa digit yang dipakai untuk MENYIMPAN. IDR = 2, jadi
 *                  Rp 50.000 tersimpan sebagai 5000000 (aturan A1).
 *   displayDecimals  berapa digit yang DITAMPILKAN. IDR = 0, karena tidak ada
 *                  orang Indonesia yang menulis "Rp 50.000,00".
 *
 * Menyimpan sen untuk IDR terasa mubazir hari ini, tapi itulah yang membuat
 * pembagian, bunga, dan mata uang lain tidak perlu migrasi skema nanti.
 */
final class Currency
{
    /** @var array<string, array{minor: int, decimals: int, symbol: string}> */
    private const TABLE = [
        'IDR' => ['minor' => 2, 'decimals' => 0, 'symbol' => 'Rp'],
        'USD' => ['minor' => 2, 'decimals' => 2, 'symbol' => '$'],
        'SGD' => ['minor' => 2, 'decimals' => 2, 'symbol' => 'S$'],
        'MYR' => ['minor' => 2, 'decimals' => 2, 'symbol' => 'RM'],
        'EUR' => ['minor' => 2, 'decimals' => 2, 'symbol' => '€'],
        'JPY' => ['minor' => 0, 'decimals' => 0, 'symbol' => '¥'],
    ];

    public static function isSupported(string $code): bool
    {
        return isset(self::TABLE[strtoupper($code)]);
    }

    /**
     * Jumlah digit minor unit — dipakai untuk menyimpan, bukan menampilkan.
     */
    public static function minorUnit(string $code): int
    {
        return self::entry($code)['minor'];
    }

    /**
     * Jumlah digit desimal yang ditampilkan ke pengguna.
     */
    public static function displayDecimals(string $code): int
    {
        return self::entry($code)['decimals'];
    }

    public static function symbol(string $code): string
    {
        return self::entry($code)['symbol'];
    }

    /**
     * Faktor pengali dari satuan utuh ke minor unit. IDR => 100.
     */
    public static function factor(string $code): int
    {
        return 10 ** self::minorUnit($code);
    }

    public static function normalize(string $code): string
    {
        $code = strtoupper(trim($code));

        self::entry($code);

        return $code;
    }

    /**
     * @return array<string>
     */
    public static function supported(): array
    {
        return array_keys(self::TABLE);
    }

    /**
     * @return array{minor: int, decimals: int, symbol: string}
     */
    private static function entry(string $code): array
    {
        $code = strtoupper(trim($code));

        return self::TABLE[$code] ?? throw new InvalidArgumentException(
            "Mata uang tidak dikenal: {$code}. Yang didukung: ".implode(', ', array_keys(self::TABLE)).'.'
        );
    }
}
