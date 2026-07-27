<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Models;

use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tagihan kepada pelanggan.
 *
 * @property string $id
 * @property Money $total_minor
 * @property CarbonImmutable $due_date
 */
class Invoice extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'contact_id',
        'number',
        'issue_date',
        'due_date',
        'total_minor',
        'status',
        'notes',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'total_minor' => MoneyCast::class.':IDR',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        return match (true) {
            $event === 'created' => AuditAction::InvoiceCreated,
            $event === 'updated' && ($after['status'] ?? null) === 'paid' => AuditAction::InvoicePaid,
            $event === 'updated' && ($after['status'] ?? null) === 'void' => AuditAction::InvoiceVoided,
            default => null,
        };
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeBelumLunas(Builder $query): void
    {
        $query->whereIn('status', ['sent', 'partial']);
    }

    public function dibayar(): Money
    {
        return Money::ofMinor(
            (int) $this->payments()->sum('amount_minor'),
            $this->total_minor->currency,
        );
    }

    public function sisa(): Money
    {
        return $this->total_minor->minus($this->dibayar());
    }

    /**
     * Umur piutang dalam hari. Negatif berarti belum jatuh tempo.
     */
    public function umurHari(): int
    {
        return (int) $this->due_date->diffInDays(now(), false);
    }

    /**
     * Kelompok umur piutang, mengikuti kebiasaan laporan aging 30/60/90.
     */
    public function kelompokUmur(): string
    {
        $umur = $this->umurHari();

        return match (true) {
            $umur <= 0 => 'Belum jatuh tempo',
            $umur <= 30 => '1-30 hari',
            $umur <= 60 => '31-60 hari',
            $umur <= 90 => '61-90 hari',
            default => 'Lebih dari 90 hari',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'sent' => 'Terkirim',
            'partial' => 'Dibayar sebagian',
            'paid' => 'Lunas',
            'void' => 'Dibatalkan',
            default => $this->status,
        };
    }
}
