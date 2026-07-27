<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Workspace;
use Closure;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Konteks tenant untuk satu request atau satu job.
 *
 * Menyimpan workspace aktif di sisi PHP (dipakai global scope Eloquent) dan
 * sekaligus menuliskannya ke sesi PostgreSQL (dipakai Row Level Security).
 * Keduanya harus bergerak bersama — kalau tidak, salah satu lapis proteksi
 * jadi bohong belaka.
 *
 * Catatan penyimpangan dari dokumen rancangan: dokumen menyebut `SET LOCAL`,
 * tapi `SET LOCAL` hanya hidup di dalam transaksi, sedangkan request Laravel
 * tidak dibungkus transaksi. Yang dipakai di sini adalah set_config(..., false)
 * yang berlaku se-sesi koneksi, DENGAN kewajiban membersihkannya:
 *
 *   - HTTP: middleware SetTenantContext memanggil clear() di terminate().
 *   - Queue: TenancyServiceProvider membersihkan sebelum dan sesudah tiap job.
 *
 * Tanpa pembersihan itu, worker antrean yang berumur panjang akan membawa
 * konteks tenant sebelumnya ke job berikutnya — persis kebocoran yang hendak
 * dicegah aturan A4.
 */
final class TenantContext
{
    private ?Workspace $workspace = null;

    private ?string $workspaceId = null;

    private ?string $userId = null;

    public function setWorkspace(Workspace $workspace): void
    {
        $this->workspace = $workspace;
        $this->setWorkspaceId((string) $workspace->getKey());
    }

    public function setWorkspaceId(string $workspaceId): void
    {
        $this->workspaceId = $workspaceId;

        if ($this->workspace !== null && (string) $this->workspace->getKey() !== $workspaceId) {
            $this->workspace = null;
        }

        $this->push();
    }

    /**
     * Identitas pengguna dipakai policy RLS pada tabel workspaces dan
     * workspace_members: seseorang boleh melihat workspace yang ia ikuti,
     * bukan hanya yang sedang aktif.
     */
    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;

        $this->push();
    }

    public function id(): ?string
    {
        return $this->workspaceId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function requireId(): string
    {
        return $this->workspaceId ?? throw new RuntimeException(
            'Tidak ada workspace aktif. Operasi ini butuh konteks tenant — '
            .'pastikan request melewati middleware SetTenantContext, atau bungkus '
            .'kode ini dengan TenantContext::runFor().'
        );
    }

    public function workspace(): ?Workspace
    {
        if ($this->workspace === null && $this->workspaceId !== null) {
            $this->workspace = Workspace::query()->find($this->workspaceId);
        }

        return $this->workspace;
    }

    public function hasWorkspace(): bool
    {
        return $this->workspaceId !== null;
    }

    public function clear(): void
    {
        $this->workspace = null;
        $this->workspaceId = null;
        $this->userId = null;

        $this->push();
    }

    /**
     * Menjalankan sepotong kode dalam konteks workspace tertentu, lalu
     * mengembalikan konteks sebelumnya — termasuk kalau kode itu melempar.
     *
     * Dipakai job antrean, perintah artisan, dan test.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Workspace|string $workspace, Closure $callback, ?string $userId = null): mixed
    {
        $previousWorkspace = $this->workspace;
        $previousWorkspaceId = $this->workspaceId;
        $previousUserId = $this->userId;

        try {
            if ($workspace instanceof Workspace) {
                $this->setWorkspace($workspace);
            } else {
                $this->setWorkspaceId($workspace);
            }

            if ($userId !== null) {
                $this->setUserId($userId);
            }

            return $callback();
        } finally {
            $this->workspace = $previousWorkspace;
            $this->workspaceId = $previousWorkspaceId;
            $this->userId = $previousUserId;
            $this->push();
        }
    }

    /**
     * Menuliskan konteks ke sesi PostgreSQL.
     *
     * Nilai kosong sengaja dipakai untuk "tidak ada", bukan NULL, supaya
     * perbandingan di policy RLS menghasilkan false dan bukan NULL — hasilnya
     * sama-sama menolak, tapi lebih mudah dibaca saat menelusuri masalah.
     */
    private function push(): void
    {
        $connection = DB::connection('pgsql');

        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->statement(
            'SELECT set_config(?, ?, false), set_config(?, ?, false)',
            ['app.workspace_id', $this->workspaceId ?? '', 'app.user_id', $this->userId ?? ''],
        );
    }
}
