<?php

declare(strict_types=1);

namespace App\Channels\Telegram\Models;

use App\Domain\Tenancy\Models\Workspace;
use App\Models\User;
use Database\Factories\TelegramLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hubungan antara satu akun Telegram dan satu pengguna Rafin.
 *
 * Tidak memakai BelongsToWorkspace: hubungan ini milik pengguna, dan satu orang
 * bisa berpindah antar buku lewat /switch tanpa menghubungkan ulang.
 *
 * @property string $id
 * @property int $telegram_user_id
 */
class TelegramLink extends Model
{
    /** @use HasFactory<TelegramLinkFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'user_id',
        'telegram_user_id',
        'chat_id',
        'active_workspace_id',
        'username',
        'linked_at',
        'unlinked_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'telegram_user_id' => 'integer',
            'chat_id' => 'integer',
            'linked_at' => 'immutable_datetime',
            'unlinked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'active_workspace_id');
    }

    /**
     * @param  Builder<TelegramLink>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->whereNull('unlinked_at');
    }

    public function isAktif(): bool
    {
        return $this->unlinked_at === null;
    }
}
