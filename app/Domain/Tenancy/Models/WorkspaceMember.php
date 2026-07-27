<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Models\User;
use Database\Factories\WorkspaceMemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan seorang pengguna di sebuah workspace.
 *
 * Tabel ini tidak memakai BelongsToWorkspace, karena justru dialah yang
 * menjawab "workspace mana saja yang boleh saya buka" sebelum ada workspace
 * aktif. Penyaringannya berdasarkan pengguna, bukan tenant.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $user_id
 * @property WorkspaceRole $role
 */
class WorkspaceMember extends Model
{
    /** @use HasFactory<WorkspaceMemberFactory> */
    use HasFactory;

    use HasUlids;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'joined_at' => 'immutable_datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
