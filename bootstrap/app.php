<?php

use App\Domain\Tenancy\Http\Middleware\EnsureWorkspaceMember;
use App\Domain\Tenancy\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Konteks tenant dipasang untuk setiap request web, termasuk yang tidak
        // membutuhkan workspace. Sesuatu yang hanya berlaku di sebagian jalur
        // akan terlupakan di jalur yang ditambahkan besok.
        $middleware->web(append: [
            SetTenantContext::class,
        ]);

        // ThrottleRequests ada di daftar prioritas Laravel dan karena itu
        // berjalan lebih awal daripada middleware biasa — termasuk lebih awal
        // daripada SetTenantContext. Tanpa penyisipan ini, batas laju yang
        // dikunci per workspace diam-diam merosot jadi per IP, karena konteks
        // tenant belum terpasang saat kuncinya dihitung.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: SetTenantContext::class,
        );

        $middleware->alias([
            'workspace' => EnsureWorkspaceMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
