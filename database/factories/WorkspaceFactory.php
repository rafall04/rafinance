<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\WorkspaceType;
use App\Domain\Tenancy\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => WorkspaceType::Personal,
            'owner_id' => User::factory(),
            'currency' => 'IDR',
            'period_start_day' => 1,
            'timezone' => 'Asia/Jakarta',
        ];
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => WorkspaceType::Business,
        ]);
    }
}
