<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityEvent>
 */
class SecurityEventFactory extends Factory
{
    protected $model = SecurityEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event' => SecurityEventType::LoginSuccess,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'geo_country' => 'ID',
            'geo_city' => 'Jakarta',
            'metadata' => ['guard' => 'web'],
        ];
    }

    public function ofType(SecurityEventType $event): static
    {
        return $this->state(fn (array $attributes): array => ['event' => $event]);
    }
}
