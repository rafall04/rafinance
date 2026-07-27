<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Budgeting\Models\Budget;
use App\Domain\Budgeting\Models\BudgetPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetPeriod>
 */
class BudgetPeriodFactory extends Factory
{
    protected $model = BudgetPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'allocated_minor' => 50_000_000,
            'carried_in_minor' => 0,
            'spent_minor' => 0,
        ];
    }
}
