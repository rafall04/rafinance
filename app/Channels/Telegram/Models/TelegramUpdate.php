<?php

declare(strict_types=1);

namespace App\Channels\Telegram\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catatan setiap update yang diterima dari Telegram.
 *
 * update_id adalah primary key, dan itulah seluruh mekanisme dedup-nya (aturan
 * A9). Telegram mengirim ulang update yang belum di-ack; tanpa ini, satu
 * gangguan jaringan berarti pengeluaran yang sama tercatat dua kali — dan
 * pengguna tidak punya cara tahu mana yang asli.
 *
 * @property int $update_id
 * @property array<string, mixed> $payload
 */
class TelegramUpdate extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'update_id';

    protected $keyType = 'int';

    protected $fillable = [
        'update_id',
        'payload',
        'status',
        'error',
        'processed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'update_id' => 'integer',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function tandaiSelesai(): void
    {
        $this->forceFill(['status' => 'processed', 'processed_at' => now()])->save();
    }

    public function tandaiGagal(string $pesan): void
    {
        $this->forceFill([
            'status' => 'failed',
            'error' => mb_substr($pesan, 0, 2000),
            'processed_at' => now(),
        ])->save();
    }
}
