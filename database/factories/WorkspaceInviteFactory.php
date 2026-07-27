<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\WorkspaceInvite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvite>
 */
class WorkspaceInviteFactory extends Factory
{
    protected $model = WorkspaceInvite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => WorkspaceRole::Editor,
            'token' => WorkspaceInvite::newToken(),
            'expires_at' => now()->addDays(7),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
