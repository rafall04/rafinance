<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Menjembatani kolom BIGINT minor unit dengan value object Money.
 *
 * Dipakai begini:
 *
 *     protected function casts(): array
 *     {
 *         return ['amount_minor' => MoneyCast::class.':IDR'];
 *     }
 *
 * Argumennya boleh kode mata uang tetap ("IDR") atau nama atribut lain pada
 * model yang menyimpan kodenya ("currency").
 *
 * set() menolak float secara terang-terangan. Tanpa penolakan itu, satu
 * `$account->balance_minor = 0.1 + 0.2` akan lolos diam-diam dan meninggalkan
 * selisih satu sen yang baru ketahuan berbulan-bulan kemudian (aturan A1).
 *
 * @implements CastsAttributes<Money|null, Money|int|null>
 */
final class MoneyCast implements CastsAttributes
{
    public function __construct(
        private readonly string $currency = 'IDR',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::ofMinor((int) $value, $this->currencyFor($model, $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof Money) {
            $expected = $this->currencyFor($model, $attributes);

            if ($value->currency !== $expected) {
                throw new InvalidArgumentException(
                    "Mata uang tidak cocok pada {$key}: menerima {$value->currency}, seharusnya {$expected}."
                );
            }

            return [$key => $value->minor];
        }

        if (is_int($value)) {
            return [$key => $value];
        }

        throw new InvalidArgumentException(sprintf(
            '%s hanya menerima Money atau int minor unit, menerima %s. '
            .'Nominal pecahan harus lewat Money::parse() atau Money::ofMajor().',
            $key,
            get_debug_type($value),
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currencyFor(Model $model, array $attributes): string
    {
        // Argumen boleh kode mata uang langsung, atau nama atribut yang memuatnya.
        if (preg_match('/^[A-Z]{3}$/', $this->currency) === 1) {
            return $this->currency;
        }

        $value = $attributes[$this->currency] ?? $model->getAttribute($this->currency);

        return is_string($value) && $value !== ''
            ? strtoupper($value)
            : (string) config('rafin.default_currency', 'IDR');
    }
}
