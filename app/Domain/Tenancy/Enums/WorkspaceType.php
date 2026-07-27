<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Enums;

enum WorkspaceType: string
{
    case Personal = 'personal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Pribadi',
            self::Business => 'Usaha',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Personal => 'Untuk mencatat keuangan sendiri atau rumah tangga.',
            self::Business => 'Untuk usaha: ada pelanggan, tagihan, dan proyek.',
        };
    }
}
