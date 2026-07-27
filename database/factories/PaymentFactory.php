<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'amount_minor' => 0,
            'currency' => 'IDR',
            'status' => 'paid',
            'paid_at' => now(),
        ];
    }
}
