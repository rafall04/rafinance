<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

/**
 * Lima tipe akun akuntansi baku.
 *
 * `normalBalance` menentukan arah saldo normal: aset dan beban bertambah di
 * debit (positif), kewajiban, modal, dan pendapatan bertambah di kredit
 * (negatif). Ini yang membuat saldo bisa dihitung dengan satu SUM tanpa
 * percabangan di mana-mana.
 */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Harta',
            self::Liability => 'Utang',
            self::Equity => 'Modal',
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }

    /**
     * +1 kalau saldo normalnya di debit, -1 kalau di kredit.
     */
    public function normalBalance(): int
    {
        return match ($this) {
            self::Asset, self::Expense => 1,
            self::Liability, self::Equity, self::Income => -1,
        };
    }

    /**
     * Akun yang saldonya ditampilkan di beranda sebagai "uang saya".
     */
    public function isBalanceSheet(): bool
    {
        return in_array($this, [self::Asset, self::Liability, self::Equity], true);
    }

    /**
     * @return array<int, AccountSubtype>
     */
    public function subtypes(): array
    {
        return match ($this) {
            self::Asset => [AccountSubtype::Cash, AccountSubtype::Bank, AccountSubtype::Ewallet, AccountSubtype::Receivable, AccountSubtype::Other],
            self::Liability => [AccountSubtype::Payable, AccountSubtype::Other],
            self::Equity => [AccountSubtype::Capital, AccountSubtype::Other],
            self::Income, self::Expense => [AccountSubtype::Other],
        };
    }
}
