<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation\Models;

use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PeriodLockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penutupan buku sampai tanggal tertentu.
 *
 * Membuka kembali tidak menghapus baris ini, melainkan mengisi reopened_at.
 * Periode yang pernah dibuka lagi adalah informasi yang berharga saat menelusuri
 * selisih — menghilangkannya berarti menghilangkan pertanyaan yang tepat.
 *
 * @property string $id
 * @property CarbonImmutable $locked_through
 */
class PeriodLock extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<PeriodLockFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        return match (true) {
            $event === 'created' => AuditAction::PeriodLocked,
            $event === 'updated' && array_key_exists('reopened_at', $after ?? []) => AuditAction::PeriodReopened,
            default => null,
        };
    }

    protected $fillable = [
        'workspace_id',
        'locked_through',
        'locked_by',
        'locked_at',
        'reason',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'locked_through' => 'immutable_date',
            'locked_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
        ];
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * @param  Builder<PeriodLock>  $query
     */
    public function scopeBerlaku(Builder $query): void
    {
        $query->whereNull('reopened_at');
    }

    public function isActive(): bool
    {
        return $this->reopened_at === null;
    }
}
