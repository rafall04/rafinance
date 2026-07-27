<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menuntut adanya workspace aktif, dan opsional sebuah kemampuan peran:
 *
 *     Route::get('/app/laporan', ...)->middleware('workspace:canRead');
 *
 * Kalau pengguna belum punya workspace sama sekali, ia dikirim ke onboarding —
 * itu keadaan yang wajar, bukan kesalahan. Kalau perannya tidak mencukupi,
 * jawabannya 404 dan bukan 403 (aturan A8): halaman yang tidak boleh dilihat
 * sebaiknya tidak mengiklankan keberadaannya.
 */
final class EnsureWorkspaceMember
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(404);
        }

        if (! $this->tenant->hasWorkspace()) {
            return redirect()->route('onboarding.workspace');
        }

        if ($ability !== null && ! $this->allows($user, $ability)) {
            abort(404);
        }

        return $next($request);
    }

    private function allows(User $user, string $ability): bool
    {
        $member = WorkspaceMember::query()
            ->where('workspace_id', $this->tenant->requireId())
            ->where('user_id', $user->getKey())
            ->first();

        $role = $member?->role;

        if (! $role instanceof WorkspaceRole || ! method_exists($role, $ability)) {
            return false;
        }

        return $role->{$ability}() === true;
    }
}
