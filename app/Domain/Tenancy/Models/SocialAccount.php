<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Enums\SocialProvider;
use App\Models\User;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu akun pihak ketiga yang tersambung ke seorang pengguna.
 *
 * @property string $id
 * @property SocialProvider $provider
 * @property string $provider_user_id
 */
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    use HasUlids;

    protected $table = 'user_social_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'provider_nickname',
        'avatar_url',
        'email_verified_by_provider',
        'last_login_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'provider' => SocialProvider::class,
            'email_verified_by_provider' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tandaiDipakai(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }
}
