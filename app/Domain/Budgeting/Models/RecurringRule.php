<?php

declare(strict_types=1);

namespace App\Domain\Budgeting\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\RecurringRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Transaksi yang berulang: sewa, gaji, iuran bulanan, langganan internet.
 *
 * Templatnya disimpan sebagai JSON, bukan sebagai kolom-kolom terpisah. Alasan
 * praktisnya: bentuk transaksi berubah antar fase, dan aturan berulang yang
 * dibuat hari ini harus tetap bisa dijalankan setelah kolom baru ditambahkan.
 *
 * @property string $id
 * @property array<string, mixed> $template
 */
class RecurringRule extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<RecurringRuleFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'label',
        'template',
        'frequency',
        'day_of_period',
        'next_run_at',
        'last_run_at',
        'is_active',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'template' => 'array',
            'day_of_period' => 'integer',
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<RecurringRule>  $query
     */
    public function scopeJatuhTempo(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    public function frekuensiLabel(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'yearly' => 'Tahunan',
            default => $this->frequency,
        };
    }

    /**
     * Kapan aturan ini jatuh tempo berikutnya.
     *
     * Tanggal di atas 28 dibatasi ke 28, bukan digeser ke akhir bulan: aturan
     * yang jatuh tanggal 31 akan melompat-lompat antara 28 Februari dan 31
     * Maret, dan orang yang menyetelnya untuk "tanggal gajian" tidak
     * mengharapkan itu.
     */
    public function hitungBerikutnya(?CarbonImmutable $dari = null): CarbonImmutable
    {
        $dari ??= CarbonImmutable::now();
        $hari = min(max($this->day_of_period, 1), 28);

        $berikutnya = match ($this->frequency) {
            'weekly' => $dari->addWeek()->startOfWeek()->addDays(min($hari, 7) - 1),
            'yearly' => $dari->addYear()->startOfMonth()->addDays($hari - 1),
            default => $dari->addMonth()->startOfMonth()->addDays($hari - 1),
        };

        return $berikutnya->startOfDay();
    }
}
