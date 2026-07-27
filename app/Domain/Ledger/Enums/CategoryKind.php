<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum CategoryKind: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }
}
