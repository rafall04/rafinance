<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation\Models;

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Transaction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Models\User;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\ReconciliationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cash opname: hasil menghitung uang sungguhan dan membandingkannya dengan buku.
 *
 * Selisih disimpan apa adanya, tidak pernah disembunyikan. Buku yang selalu
 * cocok dengan hitungan fisik adalah buku yang belum pernah dihitung — selisih
 * kecil itu wajar, dan yang berbahaya justru sistem yang membuatnya tak
 * terlihat.
 *
 * @property string $id
 * @property Money $difference_minor
 */
class Reconciliation extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<ReconciliationFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'account_id',
        'as_of_date',
        'system_balance_minor',
        'counted_balance_minor',
        'difference_minor',
        'adjustment_transaction_id',
        'performed_by',
        'note',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'as_of_date' => 'immutable_date',
            'system_balance_minor' => MoneyCast::class.':IDR',
            'counted_balance_minor' => MoneyCast::class.':IDR',
            'difference_minor' => MoneyCast::class.':IDR',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'adjustment_transaction_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function cocok(): bool
    {
        return $this->difference_minor->isZero();
    }
}
