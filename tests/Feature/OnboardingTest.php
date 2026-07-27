<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\Account;
use App\Domain\Ledger\Models\Category;
use App\Domain\Tenancy\Enums\WorkspaceRole;
use App\Domain\Tenancy\Enums\WorkspaceType;
use App\Domain\Tenancy\Http\Middleware\SetTenantContext;
use App\Domain\Tenancy\Models\Workspace;
use App\Livewire\App\Onboarding\BuatWorkspace;
use App\Models\User;
use Livewire\Livewire;

it('mengarahkan pengguna tanpa workspace ke onboarding', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/app')->assertRedirect(route('onboarding.workspace'));
});

it('membuat workspace beserta keanggotaan pemiliknya', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', 'Warung Bu Sri')
        ->set('tipe', 'business')
        ->set('awalPeriode', 25)
        ->set('timezone', 'Asia/Makassar')
        ->call('simpan')
        ->assertHasNoErrors()
        ->assertRedirect(route('app.beranda'));

    tenant()->setUserId((string) $user->getKey());
    $workspace = Workspace::query()->sole();

    expect($workspace->name)->toBe('Warung Bu Sri')
        ->and($workspace->type)->toBe(WorkspaceType::Business)
        ->and($workspace->owner_id)->toBe($user->getKey())
        ->and($workspace->currency)->toBe('IDR')
        ->and($workspace->period_start_day)->toBe(25)
        ->and($workspace->timezone)->toBe('Asia/Makassar')
        ->and($user->roleIn($workspace))->toBe(WorkspaceRole::Owner);
});

it('menyiapkan akun dan kategori awal supaya bisa langsung mencatat', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', 'Warung Bu Sri')
        ->set('tipe', 'business')
        ->set('akunAwal', ['cash', 'bank'])
        ->call('simpan')
        ->assertHasNoErrors();

    tenant()->setUserId((string) $user->getKey());
    actingInWorkspace(Workspace::query()->sole(), $user);

    expect(Account::query()->milikPengguna()->pluck('name')->all())->toBe(['Kas', 'Bank'])
        ->and(Category::query()->where('kind', 'expense')->count())->toBeGreaterThan(3)
        ->and(Category::query()->where('kind', 'income')->pluck('name')->all())->toContain('Penjualan');
});

it('menuntut setidaknya satu akun awal', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', 'Buku')
        ->set('akunAwal', [])
        ->call('simpan')
        ->assertHasErrors(['akunAwal']);
});

it('menjadikan workspace baru sebagai tenant aktif di sesi', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', 'Buku Pribadi')
        ->call('simpan');

    tenant()->setUserId((string) $user->getKey());
    $workspace = Workspace::query()->sole();

    expect(session(SetTenantContext::SESSION_KEY))->toBe($workspace->getKey());
});

it('menolak nama buku yang kosong', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', '')
        ->call('simpan')
        ->assertHasErrors(['nama' => 'required']);
});

it('menolak awal periode di atas tanggal 28', function (): void {
    $user = User::factory()->create();

    // Tanggal 29-31 tidak ada di setiap bulan; membiarkannya berarti periode
    // yang kadang hilang di bulan Februari.
    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->set('nama', 'Buku')
        ->set('awalPeriode', 31)
        ->call('simpan')
        ->assertHasErrors(['awalPeriode' => 'max']);
});

it('melewati onboarding kalau pengguna sudah punya workspace', function (): void {
    [$user] = makeWorkspaceFor();

    Livewire::actingAs($user)
        ->test(BuatWorkspace::class)
        ->assertRedirect(route('app.beranda'));
});

it('menampilkan beranda dengan layar kosong yang mengajak bertindak', function (): void {
    [$user, $workspace] = makeWorkspaceFor(attributes: ['name' => 'Warung Bu Sri']);

    $this->actingAs($user)->get('/app')
        ->assertOk()
        ->assertSee('Warung Bu Sri')
        ->assertSee('Belum ada transaksi.')
        ->assertSee('Catat lewat bot atau tekan tombol + di bawah.')
        ->assertSee('Saldo total');

    expect($workspace->currency)->toBe('IDR');
});

it('menampilkan saldo memakai kelas nominal tabular', function (): void {
    [$user] = makeWorkspaceFor();

    $halaman = $this->actingAs($user)->get('/app')->getContent();

    // Aturan desain: setiap nominal memakai IBM Plex Mono, tabular, rata kanan.
    // Kelas .nominal adalah satu-satunya jalan mendapatkannya.
    expect($halaman)->toContain('class="nominal nominal-lg')
        ->and($halaman)->toContain('Rp 0');
});
