<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionKind: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Opening = 'opening';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Masuk',
            self::Expense => 'Keluar',
            self::Transfer => 'Pindah',
            self::Adjustment => 'Penyesuaian',
            self::Opening => 'Saldo awal',
        };
    }

    /**
     * Kelas warna nominal. Arah uang disampaikan lewat warna, bukan lewat tanda
     * minus — angka negatif dengan tanda minus mudah terlewat saat menelusuri
     * daftar dengan cepat.
     */
    public function toneClass(): string
    {
        return match ($this) {
            self::Income => 'nominal-masuk',
            self::Expense => 'nominal-keluar',
            self::Transfer => 'nominal-transfer',
            self::Adjustment, self::Opening => '',
        };
    }

    public function tone(): ?string
    {
        return match ($this) {
            self::Income => 'masuk',
            self::Expense => 'keluar',
            self::Transfer => 'transfer',
            default => null,
        };
    }

    public function categoryKind(): ?CategoryKind
    {
        return match ($this) {
            self::Income => CategoryKind::Income,
            self::Expense => CategoryKind::Expense,
            default => null,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function userSelectable(): array
    {
        return [self::Expense, self::Income, self::Transfer];
    }
}
