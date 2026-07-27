<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\BudgetPeriodFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu putaran anggaran.
 *
 * `spent_minor` adalah cache yang dihitung ulang oleh job. Ia sengaja tidak
 * pernah dipakai sebagai sumber kebenaran — angka yang ditampilkan ke pengguna
 * selalu dihitung dari entries. Cache di sini hanya untuk menghindari agregat
 * berat saat memuat daftar panjang.
 *
 * @property string $id
 * @property Money $allocated_minor
 */
class BudgetPeriod extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<BudgetPeriodFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'budget_id',
        'period_start',
        'period_end',
        'allocated_minor',
        'carried_in_minor',
        'spent_minor',
        'recalculated_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'allocated_minor' => MoneyCast::class.':IDR',
            'carried_in_minor' => MoneyCast::class.':IDR',
            'spent_minor' => MoneyCast::class.':IDR',
            'recalculated_at' => 'immutable_datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function tersedia(): Money
    {
        return $this->allocated_minor->plus($this->carried_in_minor);
    }

    public function sisa(): Money
    {
        return $this->tersedia()->minus($this->spent_minor);
    }
}
