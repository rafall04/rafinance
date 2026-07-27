<?php

declare(strict_types=1);

namespace App\Domain\Capture\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\TransactionTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Favorit satu-tap. Pengeluaran yang sama berulang tiap hari tidak perlu
 * diketik ulang tiap hari.
 *
 * @property string $id
 * @property array<string, mixed> $payload
 */
class TransactionTemplate extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<TransactionTemplateFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'label',
        'payload',
        'use_count',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'use_count' => 'integer',
        ];
    }
}
