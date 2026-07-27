<?php

use App\Domain\Tenancy\Http\Middleware\EnsureWorkspaceMember;
use App\Domain\Tenancy\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

        // Di produksi Rafin duduk di belakang rantai proksi: Cloudflare
        // menerminasi TLS di edge, cloudflared meneruskan ke nginx loopback,
        // nginx meneruskan ke kontainer. Tanpa ini Laravel membaca alamat
        // proksi sebagai alamat penelepon — sama persis untuk setiap orang.
        //
        // Akibatnya bukan sekadar log yang kurang rapi: security_events
        // mencatat satu IP yang sama untuk semua percobaan masuk, sehingga
        // tidak bisa lagi membedakan satu orang salah sandi tiga kali dari
        // tiga ribu percobaan yang datang dari satu tempat. Batas laju yang
        // jatuh ke IP juga jadi satu ember untuk seluruh dunia.
        //
        // Yang dipercaya hanya jaringan privat: jembatan Docker (172.16/12),
        // loopback tempat nginx dan cloudflared duduk, serta 10/8 dan
        // 192.168/16 untuk jaga-jaga kalau susunannya berubah. Header dari
        // luar rantai itu tetap diabaikan.
        $middleware->trustProxies(
            at: array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
            ))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->alias([
            'workspace' => EnsureWorkspaceMember::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
