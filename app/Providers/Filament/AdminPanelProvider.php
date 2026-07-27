<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel admin platform.
 *
 * Seluruh isinya WAJIB berada di namespace App\Filament\Admin. Bukan soal
 * kerapian: arch test aturan A5 memindai direktori itu untuk memastikan tidak
 * ada satu pun rujukan ke model finansial. Resource yang dipindah ke luar sana
 * akan lolos dari pemindaian, dan penjaganya jadi buta.
 *
 * Panel ini juga BUKAN panel bawaan (tanpa ->default()): aplikasi pengguna
 * dibangun dengan Livewire biasa, dan Filament tidak boleh ikut campur di sana.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Rafin · Admin platform')

            // Biru Rp50.000, sama dengan aksi utama di aplikasi. Slate bawaan
            // membuat panel ini terlihat seperti produk lain yang kebetulan
            // dipasang di domain yang sama.
            ->colors([
                'primary' => Color::hex('#1f6e9c'),
                'danger' => Color::hex('#a83a48'),
                'success' => Color::hex('#2c7959'),
                'warning' => Color::hex('#c9a227'),
            ])

            // Font dan sudut disamakan dengan aplikasi. Berkas terpisah dari
            // app.css: Filament membawa Tailwind-nya sendiri, dan menyatukannya
            // akan menyeret seluruh utilitas panel ke bundel yang diunduh setiap
            // pengguna biasa.
            ->viteTheme('resources/css/filament/admin/theme.css')

            // Harus lewat API ini, bukan lewat --font-family di CSS tema:
            // Filament menyuntikkan fontnya sebagai gaya sebaris di <head>, dan
            // gaya sebaris mengalahkan lembar gaya mana pun. Percobaan pertama
            // memakai @theme dan hasilnya tetap Inter tanpa satu pun tanda
            // bahwa override-nya diabaikan.
            //
            // LocalFontProvider dengan url kosong tidak memuat apa pun dari
            // luar — @font-face Public Sans sudah ada di tema lewat fonts.css,
            // dan yang dibutuhkan di sini hanya penetapan nama keluarganya.
            ->font('Public Sans', provider: LocalFontProvider::class)
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')

            // Banner permanen. Dipasang lewat render hook supaya muncul di
            // setiap halaman tanpa kecuali — termasuk halaman yang ditambahkan
            // besok oleh orang yang belum membaca dokumen ini.
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => Blade::render('<x-admin-banner />'),
            )

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
