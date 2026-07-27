<?php

declare(strict_types=1);

use App\Domain\Billing\Services\PlatformStats;
use App\Models\User;
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Aturan A5 — panel admin platform tanpa akses ke nominal
|--------------------------------------------------------------------------
|
| Arch test menjaga kodenya; berkas ini menjaga perilakunya. Keduanya perlu:
| arch test tidak bisa melihat apa yang terjadi saat halaman benar-benar
| dirender, dan test perilaku tidak bisa mencegah seseorang menambahkan
| resource baru yang belum ada test-nya.
|
*/

beforeEach(function (): void {
    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
    catatPengeluaran(150_000, $this->kas, 'Rahasia dagang');
});

it('menolak pengguna biasa masuk ke panel admin', function (): void {
    $this->actingAs($this->pengguna)->get('/admin')->assertForbidden();
});

it('menolak tamu masuk ke panel admin', function (): void {
    $this->get('/admin')->assertRedirect();
});

it('mengizinkan admin platform membuka panel', function (): void {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin->fresh())->get('/admin')->assertSuccessful();
});

it('tidak menampilkan nominal transaksi di dasbor admin', function (): void {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $halaman = $this->actingAs($admin->fresh())->get('/admin')->getContent();

    // Nominal transaksi tadi Rp 150.000 — dalam bentuk apa pun.
    expect($halaman)->not->toContain('150.000')
        ->and($halaman)->not->toContain('15000000')
        ->and($halaman)->not->toContain('Rahasia dagang');
});

it('menampilkan banner permanen di setiap halaman admin', function (): void {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    foreach (['/admin', '/admin/workspaces', '/admin/users'] as $jalur) {
        $this->actingAs($admin->fresh())->get($jalur)
            ->assertSuccessful()
            ->assertSee('Panel ini tidak memiliki akses ke data transaksi pengguna. Seluruh aksi tercatat.');
    }
});

it('menampilkan workspace tanpa satu pun nominalnya', function (): void {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $halaman = $this->actingAs($admin->fresh())->get('/admin/workspaces')->getContent();

    expect($halaman)->toContain($this->workspace->name)
        ->and($halaman)->not->toContain('150.000')
        ->and($halaman)->not->toContain('Rahasia dagang');
});

it('hanya bisa menghitung transaksi, tidak menjumlahkannya', function (): void {
    $angka = app(PlatformStats::class);

    // Bentuk kembaliannya yang menjaga, bukan disiplin pemanggilnya: tidak ada
    // satu pun metode di sini yang bisa mengembalikan Money.
    $refleksi = new ReflectionClass(PlatformStats::class);

    foreach ($refleksi->getMethods(ReflectionMethod::IS_PUBLIC) as $metode) {
        expect((string) $metode->getReturnType())->toBe(
            'int',
            "PlatformStats::{$metode->getName()}() tidak mengembalikan int.",
        );
    }

    expect($angka->jumlahTransaksi())->toBe(2); // saldo awal + pengeluaran
});

it('tidak menyediakan cara impersonate di panel admin', function (): void {
    $berkas = Finder::create()
        ->files()
        ->in(app_path('Filament'))
        ->name('*.php');

    $diperiksa = 0;

    foreach ($berkas as $satu) {
        // Komentar dibuang lebih dulu. Aturannya soal kode, bukan soal prosa —
        // dan dokumentasi yang menjelaskan KENAPA impersonate tidak ada
        // seharusnya tidak menggagalkan test yang menjaga ketiadaannya.
        $kode = strtolower(tanpaKomentar((string) file_get_contents($satu->getRealPath())));

        expect($kode)->not->toContain('impersonate')
            ->and($kode)->not->toContain('auth::login')
            ->and($kode)->not->toContain('loginas');

        $diperiksa++;
    }

    expect($diperiksa)->toBeGreaterThan(0);
});

function tanpaKomentar(string $kode): string
{
    $keluaran = '';

    foreach (token_get_all($kode) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $keluaran .= is_array($token) ? $token[1] : $token;
    }

    return $keluaran;
}

it('membiarkan admin melihat plan dan langganan', function (): void {
    $admin = User::factory()->create();
    $admin->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($admin->fresh())->get('/admin/plans')->assertSuccessful();
    $this->actingAs($admin->fresh())->get('/admin/subscriptions')->assertSuccessful();
});
