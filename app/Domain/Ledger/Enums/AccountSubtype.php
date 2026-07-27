<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum AccountSubtype: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case Ewallet = 'ewallet';
    case Receivable = 'receivable';
    case Payable = 'payable';
    case Capital = 'capital';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Kas',
            self::Bank => 'Bank',
            self::Ewallet => 'E-wallet',
            self::Receivable => 'Piutang',
            self::Payable => 'Utang',
            self::Capital => 'Modal',
            self::Other => 'Lainnya',
        };
    }

    public function defaultColor(): string
    {
        // Warna turunan dari denominasi rupiah yang sama dengan token aplikasi,
        // supaya akun buatan pengguna tetap satu keluarga warna.
        return match ($this) {
            self::Cash => '#2E7D5B',
            self::Bank => '#1F6E9C',
            self::Ewallet => '#6B4E92',
            self::Receivable => '#C9A227',
            self::Payable => '#A83A48',
            self::Capital => '#5B6672',
            self::Other => '#5B6672',
        };
    }
}
