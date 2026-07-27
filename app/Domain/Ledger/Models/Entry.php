<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Money;
use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sisi dari sebuah transaksi.
 *
 * `amount_minor` bertanda: debit positif, kredit negatif. Jumlah seluruh entries
 * dalam satu transaksi wajib nol, dan itu ditegakkan constraint trigger
 * PostgreSQL — bukan hanya oleh service (aturan A2).
 *
 * Kolomnya sengaja TIDAK memakai MoneyCast: query agregat saldo bekerja
 * langsung di atas integer, dan cast yang setengah jalan justru membuat dua
 * kebenaran yang berbeda soal apa isi kolom ini.
 *
 * @property string $id
 * @property int $amount_minor
 */
class Entry extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'id',
        'workspace_id',
        'transaction_id',
        'account_id',
        'amount_minor',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function amount(): Money
    {
        return Money::ofMinor(
            $this->amount_minor,
            $this->account?->currency ?? (string) config('rafin.default_currency', 'IDR'),
        );
    }

    public function isDebit(): bool
    {
        return $this->amount_minor > 0;
    }
}
