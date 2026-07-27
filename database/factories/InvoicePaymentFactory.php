<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contacts\Models\Invoice;
use App\Domain\Contacts\Models\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount_minor' => 50_000_000,
            'paid_at' => now(),
        ];
    }
}
