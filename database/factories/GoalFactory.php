<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Budgeting\Models\Goal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Dana darurat', 'Motor baru', 'Umrah']),
            'target_minor' => 1_000_000_000,
            'target_date' => now()->addYear()->toDateString(),
        ];
    }
}
