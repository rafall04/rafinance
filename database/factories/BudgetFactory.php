<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Budgeting\Models\Budget;
use App\Domain\Ledger\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'period' => 'monthly',
            'amount_minor' => 50_000_000,
            'rollover' => false,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ];
    }

    public function rollover(): static
    {
        return $this->state(fn (array $attributes): array => ['rollover' => true]);
    }
}
