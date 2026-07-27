<?php

declare(strict_types=1);

namespace App\Domain\Contacts\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Support\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris tagihan.
 *
 * `qty_milli` menyimpan kuantitas dikali seribu, dengan alasan yang persis sama
 * dengan aturan A1: 1,5 jam kerja atau 0,25 kg barang harus tetap bilangan
 * bulat, karena 0,1 + 0,2 di float tidak sama dengan 0,3 dan selisih itu akan
 * muncul di total tagihan yang dikirim ke pelanggan.
 *
 * @property string $id
 * @property Money $unit_price_minor
 * @property int $qty_milli
 */
class InvoiceItem extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    use HasUlids;

    public const SKALA_QTY = 1000;

    protected $fillable = [
        'workspace_id',
        'invoice_id',
        'description',
        'qty_milli',
        'unit_price_minor',
        'sort_order',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'qty_milli' => 'integer',
            'unit_price_minor' => MoneyCast::class.':IDR',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Subtotal baris, dibulatkan ke sen terdekat.
     *
     * intdiv setelah perkalian, bukan pembagian lebih dulu: membagi dulu akan
     * membuang pecahan sebelum dikalikan, dan selisihnya menumpuk per baris.
     */
    public function subtotal(): Money
    {
        $minor = intdiv($this->unit_price_minor->minor * $this->qty_milli, self::SKALA_QTY);

        return Money::ofMinor($minor, $this->unit_price_minor->currency);
    }

    public function qty(): string
    {
        $utuh = intdiv($this->qty_milli, self::SKALA_QTY);
        $pecahan = $this->qty_milli % self::SKALA_QTY;

        return $pecahan === 0
            ? (string) $utuh
            : rtrim(rtrim(number_format($this->qty_milli / self::SKALA_QTY, 3, ',', '.'), '0'), ',');
    }
}
