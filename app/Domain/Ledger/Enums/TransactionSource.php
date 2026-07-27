<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionSource: string
{
    case Telegram = 'telegram';
    case Web = 'web';
    case PwaOffline = 'pwa_offline';
    case Import = 'import';
    case BankSync = 'bank_sync';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Telegram => 'Telegram',
            self::Web => 'Web',
            self::PwaOffline => 'Offline',
            self::Import => 'Impor',
            self::BankSync => 'Sinkron bank',
            self::Recurring => 'Berulang',
        };
    }
}
