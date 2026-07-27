<?php

declare(strict_types=1);

namespace App\Channels\Telegram\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kode enam digit yang dibuat dari web, berlaku sepuluh menit.
 *
 * Satu-satunya jalan menghubungkan akun Telegram. Alasannya: telegram_user_id
 * yang datang di webhook hanya membuktikan bahwa SESEORANG mengirim pesan ke
 * bot, bukan bahwa ia pemilik akun Rafin tertentu. Mempercayainya mentah-mentah
 * berarti siapa pun yang tahu ID Telegram orang lain bisa mengaku sebagai dia.
 */
class TelegramLinkCode extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'user_id',
        'expires_at',
        'used_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kode baru untuk seorang pengguna. Kode lama miliknya langsung hangus —
     * dua kode aktif sekaligus hanya memperbesar jendela tebakan.
     */
    public static function terbitkanUntuk(User $user, ?int $menit = null): self
    {
        static::query()->where('user_id', $user->getKey())->whereNull('used_at')->delete();

        $menit ??= (int) config('rafin.telegram.link_code_ttl_minutes', 10);

        return static::query()->create([
            'code' => str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT),
            'user_id' => $user->getKey(),
            'expires_at' => now()->addMinutes($menit),
        ]);
    }

    public function masihBerlaku(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function tandaiTerpakai(?Carbon $saat = null): void
    {
        $this->forceFill(['used_at' => $saat ?? now()])->save();
    }
}
