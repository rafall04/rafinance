<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Models;

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\InvoicePaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran atas sebuah tagihan.
 *
 * Selalu terhubung ke transaksi di buku besar. Piutang yang lunas tanpa uang
 * masuk ke akun mana pun adalah cara paling halus membuat neraca berbohong.
 *
 * @property string $id
 * @property Money $amount_minor
 */
class InvoicePayment extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<InvoicePaymentFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'invoice_id',
        'transaction_id',
        'amount_minor',
        'paid_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => MoneyCast::class.':IDR',
            'paid_at' => 'immutable_datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
