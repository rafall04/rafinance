<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Billing\Models\UsageCounter;
use App\Domain\Billing\Services\QuotaGuard;
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

    /**
     * Kuota anggota ditegakkan di model, bukan di layar yang menambahkan.
     *
     * Saat ini belum ada satu pun layar yang menambahkan anggota kedua —
     * WorkspaceInvite masih berupa model tanpa alur penerimaan. Justru itu
     * alasannya dipasang di sini: batas yang menunggu sampai fiturnya dibuat
     * adalah batas yang akan lupa dipasang saat fiturnya dibuat.
     *
     * Workspace-nya dibaca dari baris ini sendiri, bukan dari konteks tenant.
     * Keanggotaan pertama dibuat pada saat workspace baru lahir, dan pada titik
     * itu konteks tenant masih menunjuk workspace sebelumnya — memeriksa kuota
     * milik workspace yang salah lebih buruk daripada tidak memeriksa.
     */
    protected static function booted(): void
    {
        static::creating(function (self $anggota): void {
            $workspace = Workspace::query()->find($anggota->workspace_id);

            if ($workspace === null) {
                return;
            }

            app(QuotaGuard::class)->pastikanBolehMenambah(
                UsageCounter::ANGGOTA,
                workspace: $workspace,
            );
        });

        static::created(function (self $anggota): void {
            $workspace = Workspace::query()->find($anggota->workspace_id);

            if ($workspace !== null) {
                app(QuotaGuard::class)->catatPemakaian(UsageCounter::ANGGOTA, workspace: $workspace);
            }
        });

        static::deleted(function (self $anggota): void {
            $workspace = Workspace::query()->find($anggota->workspace_id);

            // Anggota yang keluar mengembalikan jatahnya. Penghitung anggota
            // bersifat kumulatif ('total'), jadi tanpa pengurangan ini sebuah
            // workspace bisa kehabisan kuota karena orang-orang yang sudah
            // lama tidak ada di sana.
            if ($workspace !== null) {
                app(QuotaGuard::class)->catatPemakaian(UsageCounter::ANGGOTA, -1, $workspace);
            }
        });
    }
}
