<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Http\Middleware\SetTenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        $this->batasLaju();

        // Worker antrean berumur panjang dan memakai ulang koneksi yang sama.
        // Tanpa pembersihan di kedua ujung, job untuk workspace A bisa membaca
        // data workspace B hanya karena kebetulan berjalan setelahnya.
        Queue::looping(fn () => $this->forget());

        Event::listen(JobProcessed::class, fn () => $this->forget());
        Event::listen(JobFailed::class, fn () => $this->forget());
        Event::listen(JobExceptionOccurred::class, fn () => $this->forget());
    }

    /**
     * Batas laju per workspace, bukan per IP.
     *
     * Per IP tidak berarti banyak di sini: satu warung bisa punya tiga orang di
     * balik satu koneksi, dan satu penyalahguna bisa punya banyak IP. Yang
     * relevan adalah berapa banyak yang ditulis ke satu buku.
     */
    private function batasLaju(): void
    {
        // Berlapis, dari yang paling tepat ke yang paling kasar. Konteks tenant
        // adalah yang dituju; sesi dan identitas pengguna adalah cadangan kalau
        // urutan middleware pernah berubah lagi; IP adalah pilihan terakhir.
        $kunci = function (Request $request): string {
            $workspace = $this->app->make(TenantContext::class)->id();

            if (is_string($workspace) && $workspace !== '') {
                return 'ws:'.$workspace;
            }

            if ($request->hasSession()) {
                $dariSesi = $request->session()->get(SetTenantContext::SESSION_KEY);

                if (is_string($dariSesi) && $dariSesi !== '') {
                    return 'ws:'.$dariSesi;
                }
            }

            $pengguna = $request->user()?->getKey();

            return $pengguna !== null
                ? 'user:'.$pengguna
                : 'ip:'.($request->ip() ?? 'tanpa-ip');
        };

        RateLimiter::for(
            'transaksi',
            fn (Request $request) => Limit::perMinute((int) config('rafin.rate_limits.transactions_per_minute', 60))
                ->by($kunci($request)),
        );

        RateLimiter::for(
            'unggah',
            fn (Request $request) => Limit::perMinute((int) config('rafin.rate_limits.uploads_per_minute', 10))
                ->by($kunci($request)),
        );

        RateLimiter::for(
            'ekspor',
            fn (Request $request) => Limit::perHour((int) config('rafin.rate_limits.exports_per_hour', 5))
                ->by($kunci($request)),
        );
    }

    private function forget(): void
    {
        $this->app->make(TenantContext::class)->clear();
    }
}
