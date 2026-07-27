<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\UsageCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'metric' => UsageCounter::TRANSAKSI,
            'period_key' => now()->format('Y-m'),
            'value' => 0,
        ];
    }
}
