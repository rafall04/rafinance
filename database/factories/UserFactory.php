<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'id',
            'timezone' => 'Asia/Jakarta',
        ];
    }

    /**
     * Memuat ulang setelah dibuat.
     *
     * Model::create() hanya membawa atribut yang benar-benar diisi, sementara
     * Model::shouldBeStrict() melempar begitu kolom yang tidak ikut terisi
     * disentuh — dan users punya banyak kolom nullable: two_factor_secret,
     * app_lock_pin_hash, telegram_user_id. Satu refresh di sini membuat model
     * hasil factory berperilaku sama dengan model yang diambil dari database.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (User $user) => $user->refresh());
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => null,
        ]);
    }
}
