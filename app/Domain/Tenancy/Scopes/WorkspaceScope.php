<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Lapis pertama penegakan aturan A4: setiap query Eloquent ke tabel workspace
 * otomatis tersaring ke workspace aktif.
 *
 * Ketika tidak ada konteks tenant, scope ini menghasilkan `1 = 0` — bukan
 * melewatkan filter. Query yang lupa konteks harus mengembalikan kosong, bukan
 * seluruh isi tabel. Ini juga yang membuat aturan A8 terpenuhi dengan
 * sendirinya: baris milik workspace lain tidak terlihat, findOrFail melempar
 * ModelNotFoundException, dan pengguna menerima 404 — bukan 403 yang justru
 * membocorkan bahwa sumber daya itu ada.
 */
final class WorkspaceScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = app(TenantContext::class)->id();

        if ($workspaceId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);
    }
}
