<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\CategoryKind;
use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property CategoryKind $kind
 */
class Category extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        return match (true) {
            $event === 'created' => AuditAction::CategoryCreated,
            $event === 'updated' && ($after['is_archived'] ?? null) => AuditAction::CategoryArchived,
            $event === 'updated' => AuditAction::CategoryUpdated,
            default => null,
        };
    }

    protected $fillable = [
        'workspace_id',
        'parent_id',
        'name',
        'kind',
        'color',
        'icon',
        'is_archived',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'is_archived' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * "Transportasi · Bensin", atau "Transportasi" kalau tidak berinduk.
     *
     * Relasi induk hanya dibaca kalau memang sudah dimuat. Mode ketat melarang
     * lazy loading, dan nama kategori dipakai di daftar panjang — satu query
     * per baris adalah cara termudah membuat halaman beranda terasa berat.
     */
    public function fullName(): string
    {
        if ($this->parent_id === null || ! $this->relationLoaded('parent')) {
            return $this->name;
        }

        return $this->parent !== null
            ? $this->parent->name.' · '.$this->name
            : $this->name;
    }
}
