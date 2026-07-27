<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contacts\Models\Contact;
use App\Domain\Contacts\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'total_minor' => 100_000_000,
            'status' => 'sent',
        ];
    }

    public function jatuhTempo(int $hariLalu = 45): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_date' => now()->subDays($hariLalu)->toDateString(),
        ]);
    }

    public function lunas(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'paid']);
    }
}
