<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Budgeting\Models\RecurringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringRule>
 */
class RecurringRuleFactory extends Factory
{
    protected $model = RecurringRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->randomElement(['Sewa ruko', 'Gaji karyawan', 'Internet']),
            'template' => ['kind' => 'expense', 'amount_minor' => 50_000_000],
            'frequency' => 'monthly',
            'day_of_period' => 1,
            'next_run_at' => now()->addMonth()->startOfMonth(),
            'is_active' => true,
        ];
    }

    public function jatuhTempo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_run_at' => now()->subDay(),
        ]);
    }
}
