<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Domain\Ledger\Models\Category;
use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Batas belanja untuk satu kategori dalam satu periode.
 *
 * @property string $id
 * @property Money $amount_minor
 */
class Budget extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'category_id',
        'period',
        'amount_minor',
        'rollover',
        'starts_on',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class.':IDR',
            'rollover' => 'boolean',
            'is_active' => 'boolean',
            'starts_on' => 'immutable_date',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        return match ($event) {
            'created' => AuditAction::BudgetCreated,
            'updated' => AuditAction::BudgetUpdated,
            default => null,
        };
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(BudgetPeriod::class);
    }

    /**
     * @param  Builder<Budget>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Awal dan akhir periode yang memuat sebuah tanggal.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentangUntuk(CarbonImmutable $tanggal): array
    {
        return $this->period === 'weekly'
            ? [$tanggal->startOfWeek(), $tanggal->endOfWeek()]
            : [$tanggal->startOfMonth(), $tanggal->endOfMonth()];
    }
}
