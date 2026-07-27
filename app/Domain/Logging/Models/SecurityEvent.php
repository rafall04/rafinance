<?php

declare(strict_types=1);

namespace App\Domain\Logging\Models;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Tenancy\Models\Workspace;
use App\Models\User;
use Database\Factories\SecurityEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak peristiwa keamanan — metadata saja, tanpa nominal (aturan A6).
 *
 * Sengaja TIDAK memakai BelongsToWorkspace meski punya kolom workspace_id.
 * Tabel ini justru satu-satunya jejak yang boleh dibaca admin platform, dan
 * itulah alasan larangan nominalnya begitu keras. Jejak yang mengandung angka
 * uang tempatnya di audit_logs, yang milik workspace dan dilindungi RLS.
 *
 * @property string $id
 * @property SecurityEventType $event
 * @property array<string, mixed>|null $metadata
 */
class SecurityEvent extends Model
{
    /** @use HasFactory<SecurityEventFactory> */
    use HasFactory;

    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'event',
        'ip',
        'user_agent',
        'geo_country',
        'geo_city',
        'device_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event' => SecurityEventType::class,
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
