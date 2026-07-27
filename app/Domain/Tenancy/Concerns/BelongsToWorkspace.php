<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Scopes\WorkspaceScope;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dipasang pada setiap model yang tabelnya bertanda (WS).
 *
 * Dua hal sekaligus: menyaring pembacaan lewat WorkspaceScope, dan mengisi
 * workspace_id saat penulisan. Yang kedua sama pentingnya — tanpa itu, satu
 * baris tanpa workspace_id akan ditolak RLS di kedalaman stack, dengan pesan
 * error yang tidak menolong siapa pun.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('workspace_id'))) {
                $model->setAttribute('workspace_id', app(TenantContext::class)->requireId());
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
