<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMember>
 */
class WorkspaceMemberFactory extends Factory
{
    protected $model = WorkspaceMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => WorkspaceRole::Editor,
            'joined_at' => now(),
        ];
    }

    public function role(WorkspaceRole $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }
}
