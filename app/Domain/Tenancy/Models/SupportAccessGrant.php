<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Models\User;
use Database\Factories\SupportAccessGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Izin akses dukungan yang diterbitkan pemilik buku, bukan diminta admin.
 *
 * Arah ini yang membedakannya dari impersonate: tidak ada tombol di panel
 * admin yang bisa membuka data seseorang. Yang ada hanya izin yang sudah
 * diberikan, terbatas waktu, dan tercatat saat dipakai.
 *
 * @property string $id
 */
class SupportAccessGrant extends Model
{
    /** @use HasFactory<SupportAccessGrantFactory> */
    use HasFactory;

    use HasUlids;

    /** Batas keras. Izin yang lebih panjang dari sehari bukan lagi "dukungan". */
    public const MAKS_JAM = 24;

    protected $fillable = [
        'workspace_id',
        'granted_by_user_id',
        'admin_user_id',
        'scope',
        'reason',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pemberi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * @param  Builder<SupportAccessGrant>  $query
     */
    public function scopeBerlaku(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function masihBerlaku(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'Dicabut',
            $this->expires_at->isPast() => 'Kedaluwarsa',
            $this->used_at !== null => 'Sedang dipakai',
            default => 'Berlaku',
        };
    }
}
