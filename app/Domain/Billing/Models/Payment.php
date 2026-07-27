<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran langganan platform.
 *
 * Perlu ditegaskan supaya tidak tertukar: nominal di sini adalah uang yang
 * dibayarkan KEPADA Rafin, bukan uang di dalam buku kas pengguna. Karena itu
 * admin platform boleh melihatnya — dan karena itu pula tabel ini tidak pernah
 * boleh dijadikan pintu masuk ke tabel transactions.
 *
 * @property string $id
 * @property Money $amount_minor
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'subscription_id',
        'amount_minor',
        'currency',
        'status',
        'provider',
        'provider_ref',
        'paid_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class.':currency',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
