<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ];
    }
}
