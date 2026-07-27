<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => SocialProvider::Google,
            'provider_user_id' => (string) fake()->unique()->numerify('##################'),
            'provider_email' => fake()->unique()->safeEmail(),
            'provider_nickname' => fake()->name(),
            'email_verified_by_provider' => true,
            'last_login_at' => now(),
        ];
    }

    public function penyedia(SocialProvider $penyedia): static
    {
        return $this->state(fn (array $attributes): array => [
            'provider' => $penyedia,
            'email_verified_by_provider' => $penyedia->verifiesEmail(),
        ]);
    }
}
