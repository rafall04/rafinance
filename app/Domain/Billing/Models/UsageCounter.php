<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Tenancy\Models\Workspace;
use Database\Factories\UsageCounterFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penghitung pemakaian per workspace per periode.
 *
 * Isinya angka hitungan, bukan nominal: berapa transaksi, berapa byte lampiran,
 * berapa anggota. Itulah sebabnya admin platform boleh melihatnya — ia
 * menjawab "seberapa besar pemakaiannya" tanpa menjawab "berapa uangnya".
 *
 * @property string $id
 */
class UsageCounter extends Model
{
    /** @use HasFactory<UsageCounterFactory> */
    use HasFactory;

    use HasUlids;

    public const TRANSAKSI = 'transactions';

    public const LAMPIRAN_BYTE = 'attachments_bytes';

    public const ANGGOTA = 'members';

    public const OCR = 'ocr_calls';

    protected $fillable = [
        'workspace_id',
        'metric',
        'period_key',
        'value',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Kunci periode untuk sebuah metrik. Yang bulanan disetel ulang tiap bulan;
     * yang kumulatif (anggota, byte lampiran) tidak pernah.
     */
    public static function kunciPeriode(string $metric): string
    {
        return in_array($metric, [self::ANGGOTA, self::LAMPIRAN_BYTE], true)
            ? 'total'
            : now()->format('Y-m');
    }
}
