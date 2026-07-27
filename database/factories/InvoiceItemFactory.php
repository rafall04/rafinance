<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contacts\Models\Invoice;
use App\Domain\Contacts\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(['Iuran bulanan', 'Pemasangan', 'Jasa produksi']),
            'qty_milli' => 1000,
            'unit_price_minor' => 100_000_000,
            'sort_order' => 0,
        ];
    }
}
