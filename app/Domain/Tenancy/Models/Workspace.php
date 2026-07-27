<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Enums\WorkspaceType;
use App\Models\User;
use App\Support\Currency;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Satu buku — pribadi maupun usaha.
 *
 * Workspace sendiri tidak memakai BelongsToWorkspace: ia bukan data di dalam
 * tenant, ia adalah tenant-nya. Pembatasan aksesnya lewat keanggotaan, baik di
 * lapis Eloquent maupun lewat policy RLS berbasis app.user_id.
 *
 * @property string $id
 * @property string $name
 * @property WorkspaceType $type
 * @property string $owner_id
 * @property string $currency
 * @property int $period_start_day
 * @property string $timezone
 */
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'owner_id',
        'currency',
        'period_start_day',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceType::class,
            'period_start_day' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(WorkspaceInvite::class);
    }

    /**
     * Peran seorang pengguna di workspace ini, atau null kalau bukan anggota.
     */
    public function roleFor(User|string $user): ?WorkspaceRole
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $member = $this->members->firstWhere('user_id', $userId)
            ?? $this->members()->where('user_id', $userId)->first();

        return $member?->role;
    }

    public function hasMember(User|string $user): bool
    {
        return $this->roleFor($user) !== null;
    }

    public function minorUnit(): int
    {
        return Currency::minorUnit($this->currency);
    }
}
