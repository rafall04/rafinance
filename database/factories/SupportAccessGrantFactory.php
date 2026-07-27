<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Models\SupportAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportAccessGrant>
 */
class SupportAccessGrantFactory extends Factory
{
    protected $model = SupportAccessGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'granted_by_user_id' => User::factory(),
            'scope' => 'read_metadata',
            'expires_at' => now()->addHours(4),
        ];
    }

    public function kedaluwarsa(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subHour()]);
    }
}
