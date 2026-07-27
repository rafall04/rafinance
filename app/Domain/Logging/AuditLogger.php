<?php

declare(strict_types=1);

namespace App\Domain\Logging;

use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Models\AuditLog;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Satu-satunya pintu menulis ke audit_logs.
 *
 * Rantai hash disusun per workspace. Gunanya bukan mencegah perubahan — role
 * aplikasi memang tidak punya hak UPDATE maupun DELETE di tabel ini — tapi
 * membuat perubahan itu KETAHUAN. Siapa pun yang menyunting atau menghapus satu
 * baris lewat jalur lain akan memutus rantainya, dan `rafin:audit:verify`
 * menunjuk persis di baris mana putusnya.
 *
 * Advisory lock per workspace membuat dua penulisan bersamaan tidak membaca
 * prev_hash yang sama. Tanpa itu rantainya bercabang, dan cabang tidak bisa
 * dibedakan dari penyuntingan.
 */
final class AuditLogger
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditAction $action,
        ?Model $auditable = null,
        ?array $before = null,
        ?array $after = null,
        ?string $workspaceId = null,
        ?string $actorId = null,
    ): AuditLog {
        $workspaceId ??= $this->resolveWorkspaceId($auditable);

        return $this->tenant->runFor($workspaceId, function () use ($action, $auditable, $before, $after, $workspaceId, $actorId): AuditLog {
            return DB::connection('pgsql')->transaction(function () use ($action, $auditable, $before, $after, $workspaceId, $actorId): AuditLog {
                // Menyerialkan penulisan per workspace, bukan seluruh tabel.
                DB::connection('pgsql')->statement(
                    'SELECT pg_advisory_xact_lock(hashtext(?))',
                    ['rafin_audit:'.$workspaceId],
                );

                $previous = AuditLog::query()
                    ->where('workspace_id', $workspaceId)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                $createdAt = Carbon::now();
                $stamp = $createdAt->format('Y-m-d H:i:s.u');
                $auditableId = $auditable?->getKey();
                $auditableId = is_scalar($auditableId) ? (string) $auditableId : null;

                return AuditLog::query()->create([
                    'id' => (string) Str::ulid(),
                    'workspace_id' => $workspaceId,
                    'actor_user_id' => $actorId ?? auth()->id(),
                    'action' => $action,
                    'auditable_type' => $auditable !== null ? $auditable::class : null,
                    'auditable_id' => $auditableId,
                    'before' => $before,
                    'after' => $after,
                    'ip' => request()->ip(),
                    'prev_hash' => $previous?->hash,
                    'hash' => AuditLog::computeHash($previous?->hash, $action->value, $auditableId, $stamp),
                    'created_at' => $stamp,
                ]);
            });
        }, $actorId ?? (is_string(auth()->id()) ? auth()->id() : null));
    }

    /**
     * Memeriksa keutuhan rantai sebuah workspace.
     *
     * @return array{total: int, ok: bool, broken: array<int, array{id: string, alasan: string}>}
     */
    public function verify(string $workspaceId): array
    {
        return $this->tenant->runFor($workspaceId, function () use ($workspaceId): array {
            $entries = AuditLog::query()
                ->where('workspace_id', $workspaceId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $broken = [];
            $expectedPrev = null;

            foreach ($entries as $entry) {
                if ($entry->prev_hash !== $expectedPrev) {
                    $broken[] = [
                        'id' => $entry->id,
                        'alasan' => 'prev_hash tidak menyambung ke baris sebelumnya',
                    ];
                } elseif ($entry->hash !== $entry->expectedHash()) {
                    $broken[] = [
                        'id' => $entry->id,
                        'alasan' => 'isi baris tidak cocok dengan hash-nya',
                    ];
                }

                $expectedPrev = $entry->hash;
            }

            return [
                'total' => $entries->count(),
                'ok' => $broken === [],
                'broken' => $broken,
            ];
        });
    }

    private function resolveWorkspaceId(?Model $auditable): string
    {
        $fromModel = $auditable?->getAttribute('workspace_id');

        return is_string($fromModel) && $fromModel !== ''
            ? $fromModel
            : $this->tenant->requireId();
    }
}
