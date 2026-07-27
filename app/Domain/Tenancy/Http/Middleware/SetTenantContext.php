<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Http\Middleware;

use App\Domain\Tenancy\Models\Workspace;
use App\Domain\Tenancy\Models\WorkspaceMember;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memasang konteks tenant di awal request dan melepasnya di akhir.
 *
 * Kunci sesi disimpan per pengguna. Kalau tidak, dua akun yang bergantian di
 * satu peramban bisa saling mewarisi workspace terakhir.
 */
final class SetTenantContext
{
    public const SESSION_KEY = 'rafin.workspace_id';

    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // Urutannya penting: policy RLS pada tabel workspaces membaca
        // app.user_id, jadi identitas harus terpasang sebelum workspace dicari.
        $this->tenant->setUserId((string) $user->getKey());

        $workspace = $this->resolve($request, $user);

        if ($workspace !== null) {
            $this->tenant->setWorkspace($workspace);
            $request->session()->put(self::SESSION_KEY, $workspace->getKey());
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->tenant->clear();
    }

    private function resolve(Request $request, User $user): ?Workspace
    {
        $requested = $request->session()->get(self::SESSION_KEY);

        if (is_string($requested) && $requested !== '') {
            $membership = WorkspaceMember::query()
                ->where('user_id', $user->getKey())
                ->where('workspace_id', $requested)
                ->first();

            if ($membership !== null) {
                return $membership->workspace;
            }

            // Keanggotaan dicabut, atau isi sesi tidak lagi sah. Buang diam-diam
            // dan jatuh ke workspace lain yang memang boleh dibuka.
            $request->session()->forget(self::SESSION_KEY);
        }

        return WorkspaceMember::query()
            ->where('user_id', $user->getKey())
            ->oldest('joined_at')
            ->first()
            ?->workspace;
    }
}
