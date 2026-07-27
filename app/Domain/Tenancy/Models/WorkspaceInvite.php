<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Concerns\BelongsToWorkspace;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use Database\Factories\WorkspaceInviteFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Undangan bergabung ke sebuah workspace.
 *
 * Ini tabel (WS) pertama di Rafin: ia punya workspace_id, memakai global scope
 * Eloquent, dan dilindungi policy RLS. FASE 1 dan seterusnya tinggal mengikuti
 * pola yang sama.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $email
 * @property WorkspaceRole $role
 * @property string $token
 */
class WorkspaceInvite extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<WorkspaceInviteFactory> */
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'email',
        'role',
        'token',
        'expires_at',
        'invited_by',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(48);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function markAccepted(?Carbon $at = null): void
    {
        $this->forceFill(['accepted_at' => $at ?? now()])->save();
    }
}
