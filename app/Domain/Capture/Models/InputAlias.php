<?php

declare(strict_types=1);

namespace App\Domain\Capture\Models;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Projects\Models\Project;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\InputAliasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pintasan buatan pengguna: "bbm" berarti Transportasi dari Kas.
 *
 * Ini yang membuat parser terasa pintar tanpa satu pun panggilan LLM. Setiap
 * orang punya sepuluh sampai dua puluh pengeluaran yang berulang terus, dan
 * begitu ia mengajarkannya sekali, satu kata sudah cukup selamanya.
 *
 * @property string $id
 */
class InputAlias extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<InputAliasFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'keyword',
        'category_id',
        'account_id',
        'project_id',
        'use_count',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['use_count' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pakai(): void
    {
        $this->increment('use_count');
    }
}
