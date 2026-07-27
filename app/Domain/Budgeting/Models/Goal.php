<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Domain\Ledger\Models\Account;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Target tabungan.
 *
 * @property string $id
 * @property Money $target_minor
 */
class Goal extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'name',
        'target_minor',
        'account_id',
        'target_date',
        'achieved_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'target_minor' => MoneyCast::class.':IDR',
            'target_date' => 'immutable_date',
            'achieved_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function terkumpul(): Money
    {
        return $this->account?->balance() ?? Money::zero('IDR');
    }

    /**
     * Persentase, dibatasi 100 supaya bilah kemajuan tidak meluber saat target
     * terlampaui.
     */
    public function persentase(): int
    {
        if ($this->target_minor->isZero()) {
            return 0;
        }

        return (int) min(100, round($this->terkumpul()->minor / $this->target_minor->minor * 100));
    }

    public function tercapai(): bool
    {
        return $this->achieved_at !== null
            || $this->terkumpul()->compareTo($this->target_minor) >= 0;
    }
}
