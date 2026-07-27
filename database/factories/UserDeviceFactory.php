<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Models\UserDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserDevice>
 */
class UserDeviceFactory extends Factory
{
    protected $model = UserDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Android · Chrome', 'iPhone · Safari', 'Windows · Edge']),
            'session_id' => Str::random(40),
            'last_ip' => fake()->ipv4(),
            'last_user_agent' => fake()->userAgent(),
            'last_seen_at' => now(),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
        ]);
    }
}
