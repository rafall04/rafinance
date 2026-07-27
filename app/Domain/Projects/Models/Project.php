<?php

declare(strict_types=1);

namespace App\Domain\Projects\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wadah biaya dan pendapatan per pekerjaan.
 *
 * Ada karena tim produksi event perlu tahu untung-rugi per job, bukan per bulan
 * — dan itu pertanyaan yang tidak bisa dijawab kategori.
 *
 * @property string $id
 */
class Project extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'name',
        'status',
        'starts_on',
        'ends_on',
        'budget_minor',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'budget_minor' => MoneyCast::class.':IDR',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @param  Builder<Project>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
