<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Channels\Telegram\Models\TelegramLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TelegramLink>
 */
class TelegramLinkFactory extends Factory
{
    protected $model = TelegramLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'telegram_user_id' => fake()->unique()->numberBetween(100_000, 999_999_999),
            'chat_id' => fake()->numberBetween(100_000, 999_999_999),
            'username' => fake()->userName(),
            'linked_at' => now(),
        ];
    }
}
