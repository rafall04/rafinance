<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Ledger\Enums\AccountSubtype;
use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Services\RecordOpeningBalance;
use App\Domain\Logging\Concerns\Auditable;
use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu tempat uang: kas, rekening bank, e-wallet, piutang, utang, modal.
 *
 * Saldo tidak disimpan sebagai kolom. Ia selalu dihitung dari entries, karena
 * kolom saldo yang di-cache adalah kolom yang cepat atau lambat akan berbeda
 * dari buku besarnya — dan begitu itu terjadi, tidak ada cara memberi tahu mana
 * yang benar.
 *
 * @property string $id
 * @property AccountType $type
 * @property AccountSubtype $subtype
 * @property Money $opening_balance_minor
 */
class Account extends Model
{
    use Auditable;
    use BelongsToWorkspace;

    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use HasUlids;

    protected static function booted(): void
    {
        // Saldo awal dicatat sebagai transaksi lewat event, bukan dari tempat
        // pemanggilnya. Akun lahir dari banyak jalur — onboarding, halaman
        // akun, impor, factory di test — dan satu jalur yang lupa memanggilnya
        // berarti satu akun dengan harta tanpa asal.
        static::created(function (Account $akun): void {
            app(RecordOpeningBalance::class)($akun);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    protected function auditActionFor(string $event, ?array $before, ?array $after): ?AuditAction
    {
        // Akun sistem dibuat mesin, bukan orang. Mencatatnya hanya membuat
        // riwayat ramai tanpa menjawab pertanyaan siapa pun.
        if ($this->is_system) {
            return null;
        }

        return match (true) {
            $event === 'created' => AuditAction::AccountCreated,
            $event === 'updated' && ($after['is_archived'] ?? null) => AuditAction::AccountArchived,
            $event === 'updated' => AuditAction::AccountUpdated,
            default => null,
        };
    }

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'subtype',
        'currency',
        'opening_balance_minor',
        'color',
        'sort_order',
        'is_archived',
        'is_system',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'opening_balance_minor' => MoneyCast::class.':currency',
            'is_archived' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeAktif(Builder $query): void
    {
        $query->where('is_archived', false);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeUang(Builder $query): void
    {
        // Akun yang berisi "uang saya" — yang tampil sebagai chip di beranda.
        $query->where('is_system', false)->whereIn('subtype', [
            AccountSubtype::Cash->value,
            AccountSubtype::Bank->value,
            AccountSubtype::Ewallet->value,
        ]);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeMilikPengguna(Builder $query): void
    {
        $query->where('is_system', false);
    }

    /**
     * Akun lawan yang dibuat otomatis dan tidak pernah muncul di daftar akun
     * pengguna. Pengguna memilih satu akun dan satu kategori; sisi kedua dari
     * pembukuan double-entry-nya ada di sini.
     */
    public static function sistem(AccountType $type, string $currency = 'IDR'): self
    {
        $nama = match ($type) {
            AccountType::Expense => 'Beban',
            AccountType::Income => 'Pendapatan',
            AccountType::Equity => 'Modal awal',
            default => $type->label(),
        };

        return static::query()->firstOrCreate(
            ['type' => $type->value, 'is_system' => true],
            [
                'name' => $nama,
                'subtype' => AccountSubtype::Other->value,
                'currency' => $currency,
                'opening_balance_minor' => 0,
                'sort_order' => 900,
            ],
        );
    }

    /**
     * Saldo mentah dalam konvensi debit-positif, seperti tersimpan di entries.
     *
     * Saldo awal TIDAK ditambahkan lagi di sini: ia sudah masuk sebagai
     * transaksi berjenis `opening` saat akun dibuat, lengkap dengan sisi
     * lawannya di modal. Menambahkannya dua kali adalah cara paling mudah
     * membuat saldo tidak cocok dengan buku besarnya sendiri.
     *
     * Transaksi berstatus void tetap dihitung, dan itu juga disengaja. Aturan
     * A3 mengoreksi dengan membuat transaksi pembalik, bukan dengan menghapus:
     * yang lama tetap ada, yang baru meniadakannya, jumlahnya nol. Mengeluarkan
     * yang void justru akan menghitung pembalikannya dua kali.
     *
     * Yang tidak dihitung hanyalah draft — ia memang belum pernah masuk buku.
     */
    public function signedBalance(): Money
    {
        $sum = (int) $this->entries()
            ->whereHas('transaction', fn (Builder $q) => $q->where('status', '!=', TransactionStatus::Draft->value))
            ->sum('amount_minor');

        return Money::ofMinor($sum, $this->currency);
    }

    /**
     * Saldo sebagaimana dibaca manusia: positif berarti "punya" untuk harta,
     * dan positif berarti "berutang" untuk kewajiban.
     */
    public function balance(): Money
    {
        return $this->signedBalance()->multipliedBy($this->type->normalBalance());
    }

    public function color(): string
    {
        return $this->color ?: $this->subtype->defaultColor();
    }
}
