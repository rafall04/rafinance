<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum TransactionStatus: string
{
    /** Belum masuk buku. Tidak mempengaruhi saldo. */
    case Draft = 'draft';

    /** Sudah masuk buku. Terkunci — koreksinya lewat transaksi pembalik (A3). */
    case Posted = 'posted';

    /** Dibatalkan lewat pembalikan. Tetap ada, tetap terlihat, tidak dihapus. */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Tercatat',
            self::Void => 'Dibatalkan',
        };
    }

    public function affectsBalance(): bool
    {
        return $this === self::Posted;
    }
}
