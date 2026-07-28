<?php

declare(strict_types=1);

use App\Channels\Telegram\Http\Controllers\WebhookController;
use App\Http\Controllers\App\EksporController;
use App\Http\Controllers\App\ShareTargetController;
use App\Http\Controllers\App\SimpanTransaksiController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BerandaController;
use App\Livewire\App\Akun;
use App\Livewire\App\Anggaran;
use App\Livewire\App\Beranda;
use App\Livewire\App\Inbox;
use App\Livewire\App\Keamanan;
use App\Livewire\App\KunciAplikasi;
use App\Livewire\App\Lainnya;
use App\Livewire\App\Langganan;
use App\Livewire\App\Laporan;
use App\Livewire\App\LogAktivitas;
use App\Livewire\App\Onboarding\BuatWorkspace;
use App\Livewire\App\Pengaturan\HubungkanTelegram;
use App\Livewire\App\Proyek;
use App\Livewire\App\Tagihan;
use App\Livewire\App\Tambah;
use Illuminate\Support\Facades\Route;

// Halaman depan untuk tamu; yang sudah masuk langsung ke buku kasnya.
Route::get('/', BerandaController::class)->name('beranda');

// Publik dan sengaja spesifik. Janji privasi yang tidak menyebut nama tabel
// bukan janji, melainkan pemasaran.
Route::view('/transparansi', 'transparansi')->name('transparansi');

/*
|--------------------------------------------------------------------------
| Webhook Telegram
|--------------------------------------------------------------------------
|
| Di luar middleware auth dan di luar 'web': tidak ada sesi, tidak ada CSRF,
| tidak ada cookie. Yang membuktikan keasliannya hanya header rahasia, dan
| pemeriksaannya ada di dalam controller.
|
*/

Route::post('/webhooks/telegram', WebhookController::class)
    ->middleware('throttle:120,1')
    ->name('telegram.webhook');

/*
|--------------------------------------------------------------------------
| Masuk lewat penyedia pihak ketiga
|--------------------------------------------------------------------------
|
| Di dalam grup 'web' karena Socialite menyimpan parameter `state` di sesi —
| itulah yang mengikat permintaan keluar dengan kembaliannya, dan mencegah orang
| lain menyodorkan kode otorisasi miliknya ke sesi korban.
|
| Tanpa middleware auth: rute yang sama melayani masuk, mendaftar, dan
| menyambungkan dari halaman Keamanan. Yang membedakan adalah ada tidaknya sesi,
| dan itu diputuskan di dalam controller.
|
*/

Route::middleware('web')->group(function (): void {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:20,1')
        ->name('oauth.redirect');

    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('oauth.callback');

    Route::delete('/auth/{provider}', [SocialAuthController::class, 'destroy'])
        ->middleware(['auth', 'throttle:20,1'])
        ->name('oauth.destroy');
});

/*
|--------------------------------------------------------------------------
| Aplikasi pengguna
|--------------------------------------------------------------------------
|
| scopeBindings() dinyalakan untuk seluruh grup, bukan per rute. Tanpa itu,
| /app/akun/{akun}/transaksi/{transaksi} akan mencari transaksi di seluruh
| tabel dan bukan hanya di dalam akun induknya — dan celah semacam itu muncul
| dari rute yang ditambahkan belakangan, bukan dari yang ditulis hari ini.
|
*/

Route::middleware(['auth', 'verified'])
    ->scopeBindings()
    ->group(function (): void {
        Route::get('/onboarding', BuatWorkspace::class)->name('onboarding.workspace');

        // 'terbuka' menegakkan kunci aplikasi di server. Ia dipasang di grup,
        // bukan di masing-masing rute, karena halaman yang ditambahkan besok
        // tidak akan mengingatkan siapa pun bahwa ia perlu dijaga juga.
        // Middleware-nya sendiri yang melewatkan /app/kunci, supaya
        // pengalihannya tidak berputar.
        Route::prefix('app')
            ->name('app.')
            ->middleware(['workspace', 'terbuka'])
            ->group(function (): void {
                Route::get('/', Beranda::class)->name('beranda');
                Route::get('/tambah', Tambah::class)->name('tambah');
                Route::get('/akun', Akun::class)->name('akun');
                Route::get('/inbox', Inbox::class)->name('inbox');
                Route::get('/laporan', Laporan::class)->name('laporan');
                Route::get('/anggaran', Anggaran::class)->name('anggaran');
                Route::get('/proyek', Proyek::class)->name('proyek');
                Route::get('/tagihan', Tagihan::class)->name('tagihan');
                Route::get('/lainnya', Lainnya::class)->name('lainnya');
                Route::get('/keamanan', Keamanan::class)->name('keamanan');
                Route::get('/langganan', Langganan::class)->name('langganan');
                Route::get('/log', LogAktivitas::class)->name('log');
                Route::get('/kunci', KunciAplikasi::class)->name('kunci');
                Route::get('/pengaturan/telegram', HubungkanTelegram::class)->name('pengaturan.telegram');

                // Ekspor adalah satu-satunya jalur data keluar utuh, jadi
                // batasnya paling ketat: lima per jam per workspace.
                Route::get('/ekspor', EksporController::class)
                    ->middleware('throttle:ekspor')
                    ->name('ekspor');

                // Jalur masuk PWA. Batas lajunya per workspace, bukan per IP:
                // satu warung bisa punya tiga orang di balik satu koneksi.
                Route::post('/transaksi', SimpanTransaksiController::class)
                    ->middleware('throttle:transaksi')
                    ->name('transaksi.simpan');

                Route::post('/share', ShareTargetController::class)
                    ->middleware('throttle:unggah')
                    ->name('share');
            });
    });
