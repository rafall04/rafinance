<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Definisi plan beserta batasnya.
 *
 * @property string $id
 * @property Money $price_minor
 * @property array<string, mixed> $limits
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasUlids;

    /** Nilai batas yang berarti "tanpa batas". */
    public const TANPA_BATAS = -1;

    protected $fillable = [
        'code',
        'name',
        'price_minor',
        'currency',
        'interval',
        'is_public',
        'sort_order',
        'limits',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'price_minor' => MoneyCast::class.':currency',
            'is_public' => 'boolean',
            'limits' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @param  Builder<Plan>  $query
     */
    public function scopePublik(Builder $query): void
    {
        $query->where('is_public', true)->orderBy('sort_order');
    }

    public function batas(string $metric, int $bawaan = self::TANPA_BATAS): int
    {
        $nilai = $this->limits[$metric] ?? $bawaan;

        return is_numeric($nilai) ? (int) $nilai : $bawaan;
    }

    public function fitur(string $nama): bool
    {
        return (bool) ($this->limits[$nama] ?? false);
    }

    public function gratis(): bool
    {
        return $this->price_minor->isZero();
    }
}
