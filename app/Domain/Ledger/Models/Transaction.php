<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Ledger\Enums\TransactionKind;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Projects\Models\Project;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu peristiwa keuangan. Nominalnya tidak ada di sini — ia hidup di entries,
 * karena satu peristiwa selalu menyentuh setidaknya dua akun (aturan A2).
 *
 * @property string $id
 * @property CarbonImmutable $booked_date
 * @property TransactionKind $kind
 * @property TransactionStatus $status
 * @property TransactionSource $source
 */
class Transaction extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    use HasUlids;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        if ($event === 'created') {
            return AuditAction::TransactionCreated;
        }

        if ($event !== 'updated') {
            // Draft yang dibuang tidak pernah menyentuh saldo, jadi tidak
            // menambah apa pun pada riwayat selain kebisingan.
            return null;
        }

        return match ($after['status'] ?? null) {
            TransactionStatus::Posted->value => AuditAction::TransactionPosted,
            TransactionStatus::Void->value => AuditAction::TransactionVoided,
            default => AuditAction::TransactionUpdated,
        };
    }

    protected $fillable = [
        'id',
        'workspace_id',
        'booked_date',
        'description',
        'kind',
        'category_id',
        'project_id',
        'contact_id',
        'status',
        'source',
        'source_ref',
        'raw_input',
        'reverses_transaction_id',
        'created_by',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'booked_date' => 'immutable_date',
            'kind' => TransactionKind::class,
            'status' => TransactionStatus::class,
            'source' => TransactionSource::class,
            'posted_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class)->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_transaction_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeTercatat(Builder $query): void
    {
        $query->where('status', TransactionStatus::Posted);
    }

    /**
     * Menyisihkan transaksi saldo awal.
     *
     * Saldo awal adalah transaksi sungguhan — ia harus ada supaya neraca
     * seimbang — tapi ia bukan aktivitas. Menghitungnya sebagai "transaksi
     * bulan ini" akan membuat setiap buku baru terlihat seperti sudah ramai
     * padahal belum ada apa-apa.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeBukanSaldoAwal(Builder $query): void
    {
        $query->where('kind', '!=', TransactionKind::Opening->value);
    }

    /**
     * Nominal transaksi: jumlah seluruh entries yang bernilai positif.
     *
     * Karena totalnya selalu nol, sisi debit dan sisi kredit sama besar — jadi
     * salah satunya sudah mewakili "berapa besar transaksi ini".
     */
    public function amount(): Money
    {
        // Mata uang dibaca dari konteks tenant, bukan dari relasi workspace:
        // seluruh isi satu buku memakai satu mata uang, dan memuat relasinya
        // per transaksi berarti satu query tambahan untuk setiap baris daftar.
        $currency = app(TenantContext::class)->workspace()?->currency
            ?? (string) config('rafin.default_currency', 'IDR');

        $debit = $this->entries
            ->filter(fn (Entry $entry): bool => $entry->amount_minor > 0)
            ->sum('amount_minor');

        return Money::ofMinor((int) $debit, $currency);
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted;
    }

    public function isVoid(): bool
    {
        return $this->status === TransactionStatus::Void;
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::Draft;
    }
}
