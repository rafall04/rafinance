<?php

declare(strict_types=1);

namespace App\Domain\Capture\Enums;

enum ParseStatus: string
{
    /** Masuk, belum diproses. */
    case Pending = 'pending';

    /** Terbaca dan sudah jadi transaksi. */
    case Parsed = 'parsed';

    /** Tidak terbaca — menunggu dilengkapi manusia, bukan ditolak. */
    case Failed = 'failed';

    /** Ditutup tanpa jadi transaksi. Bukan sampah, memang bukan transaksi. */
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Parsed => 'Selesai',
            self::Failed => 'Perlu dilengkapi',
            self::Dismissed => 'Diabaikan',
        };
    }
}
