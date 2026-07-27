<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Models\Workspace;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 */
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'status',
        'current_period_start',
        'current_period_end',
        'provider',
        'provider_ref',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'immutable_datetime',
            'current_period_end' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Aktif termasuk masa tenggang.
     *
     * Buku kas seseorang tidak dikunci pada detik pembayaran gagal. Orang yang
     * kartunya kedaluwarsa tetap perlu mencatat pengeluaran hari itu, dan
     * memutus aksesnya justru membuat pembukuannya berlubang — yang merugikan
     * dia, bukan kami.
     */
    public function aktif(): bool
    {
        return in_array($this->status, ['trialing', 'active', 'grace'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'trialing' => 'Masa coba',
            'active' => 'Aktif',
            'past_due' => 'Tertunggak',
            'grace' => 'Masa tenggang',
            'canceled' => 'Dibatalkan',
            default => $this->status,
        };
    }
}
