<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Models\User;
use Database\Factories\UserDeviceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perangkat yang pernah masuk ke akun seorang pengguna.
 *
 * Milik pengguna, bukan workspace — satu orang bisa membuka beberapa workspace
 * dari satu ponsel. Isinya sengaja hanya metadata: label, sesi, IP, dan waktu.
 * Tidak ada satu pun nominal di sini, sejalan dengan aturan A6.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $label
 */
class UserDevice extends Model
{
    /** @use HasFactory<UserDeviceFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'user_id',
        'label',
        'session_id',
        'last_ip',
        'last_user_agent',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
