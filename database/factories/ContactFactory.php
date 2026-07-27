<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'type' => 'customer',
            'phone' => '08'.fake()->numerify('##########'),
        ];
    }

    public function vendor(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => 'vendor']);
    }
}
