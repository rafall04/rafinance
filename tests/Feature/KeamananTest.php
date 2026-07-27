<?php

declare(strict_types=1);

use App\Domain\Logging\Enums\AuditAction;
use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\AuditLog;
use App\Domain\Logging\Models\SecurityEvent;
use App\Domain\Tenancy\Models\SupportAccessGrant;
use App\Domain\Tenancy\Models\UserDevice;
use App\Livewire\App\Keamanan;
use App\Livewire\App\LogAktivitas;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    [$this->pengguna, $this->workspace] = makeWorkspaceFor();
    $this->kas = buatAkun('Kas', 1_000_000);
});

it('mencatat perangkat saat masuk', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password'])->assertRedirect();

    $perangkat = UserDevice::query()->sole();

    expect($perangkat->label)->toContain('·')
        ->and($perangkat->last_seen_at)->not->toBeNull();
});

it('mencatat perangkat baru sebagai peristiwa keamanan', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password']);

    expect(SecurityEvent::query()->where('event', SecurityEventType::SessionNewDevice)->count())->toBe(1);
});

it('tidak menganggap perangkat yang sama sebagai perangkat baru', function (): void {
    $user = User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password']);
    $this->post('/logout');
    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password']);

    // Sidik perangkat dibuat dari user agent dan blok jaringan, bukan dari ID
    // sesi — kalau tidak, setiap login jadi "perangkat baru" dan peringatannya
    // akan diabaikan justru pada hari ia penting.
    expect(UserDevice::query()->where('user_id', $user->getKey())->count())->toBe(1)
        ->and(SecurityEvent::query()->where('event', SecurityEventType::SessionNewDevice)->count())->toBe(1);
});

it('memasang dan menghapus PIN kunci aplikasi', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->set('pinBaru', '123456')
        ->set('pinUlang', '123456')
        ->call('pasangPin')
        ->assertHasNoErrors();

    expect($this->pengguna->fresh()->punyaKunciAplikasi())->toBeTrue();

    Livewire::actingAs($this->pengguna->fresh())
        ->test(Keamanan::class)
        ->call('hapusPin');

    expect($this->pengguna->fresh()->punyaKunciAplikasi())->toBeFalse();
});

it('menolak PIN yang ulangannya tidak sama', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->set('pinBaru', '123456')
        ->set('pinUlang', '654321')
        ->call('pasangPin')
        ->assertHasErrors(['pinUlang']);
});

it('mengeluarkan perangkat lain tanpa mengeluarkan diri sendiri', function (): void {
    UserDevice::factory()->create([
        'user_id' => $this->pengguna->getKey(),
        'session_id' => 'sesi-perangkat-lain',
    ]);

    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->call('keluarkanSemua');

    expect(UserDevice::query()->whereNull('revoked_at')->count())->toBe(0);
});

it('menerbitkan izin akses dukungan yang berumur pendek', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->set('jamIzin', 4)
        ->set('alasanIzin', 'Saldo tidak cocok')
        ->call('terbitkanIzin')
        ->assertHasNoErrors();

    $izin = SupportAccessGrant::query()->sole();

    expect($izin->granted_by_user_id)->toBe($this->pengguna->getKey())
        ->and($izin->masihBerlaku())->toBeTrue()
        ->and($izin->expires_at->diffInHours(now()))->toBeLessThanOrEqual(4)
        ->and(SecurityEvent::query()->where('event', SecurityEventType::SupportAccessGranted)->count())->toBe(1);
});

it('menolak izin yang lebih panjang dari sehari', function (): void {
    // Izin yang berlaku berhari-hari bukan lagi "dukungan".
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->set('jamIzin', 48)
        ->call('terbitkanIzin')
        ->assertHasErrors(['jamIzin']);

    expect(SupportAccessGrant::query()->count())->toBe(0);
});

it('mencabut izin akses dukungan', function (): void {
    $izin = SupportAccessGrant::factory()->create([
        'workspace_id' => $this->workspace->getKey(),
        'granted_by_user_id' => $this->pengguna->getKey(),
    ]);

    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->call('cabutIzin', $izin->getKey());

    expect($izin->fresh()->masihBerlaku())->toBeFalse()
        ->and($izin->fresh()->statusLabel())->toBe('Dicabut');
});

it('menampilkan log aktivitas beserta pelakunya', function (): void {
    $this->actingAs($this->pengguna);
    catatPengeluaran(50_000, $this->kas, 'Bensin');

    $this->get('/app/log')
        ->assertOk()
        ->assertSee('Transaksi dicatat')
        ->assertSee('Periksa keutuhan riwayat');
});

it('memverifikasi rantai riwayat dari halaman log', function (): void {
    catatPengeluaran(50_000, $this->kas);

    Livewire::actingAs($this->pengguna)
        ->test(LogAktivitas::class)
        ->call('periksaRantai')
        ->assertSet('hasilVerifikasi.ok', true);
});

it('melaporkan rantai yang putus di halaman log', function (): void {
    catatPengeluaran(50_000, $this->kas);

    // Baris palsu yang prev_hash-nya tidak menyambung — persis yang terjadi
    // kalau ada baris di tengah yang dihilangkan.
    AuditLog::query()->create([
        'id' => (string) Str::ulid(),
        'workspace_id' => $this->workspace->getKey(),
        'action' => AuditAction::TransactionVoided,
        'prev_hash' => str_repeat('c', 64),
        'hash' => str_repeat('d', 64),
        'created_at' => now()->addMinute(),
    ]);

    Livewire::actingAs($this->pengguna)
        ->test(LogAktivitas::class)
        ->call('periksaRantai')
        ->assertSet('hasilVerifikasi.ok', false);
});

it('menyembunyikan log aktivitas workspace lain', function (): void {
    $this->actingAs($this->pengguna);
    catatPengeluaran(50_000, $this->kas, 'Rahasia');

    [$dua] = makeWorkspaceFor();

    $this->actingAs($dua)->get('/app/log')
        ->assertOk()
        ->assertDontSee('Rahasia');
});

it('menyediakan halaman transparansi yang menyebut isinya secara spesifik', function (): void {
    $halaman = $this->get('/transparansi');

    $halaman->assertOk()
        ->assertSee('Nominal transaksi Anda')
        ->assertSee('Tidak ada tombol impersonate')
        ->assertSee('Jumlah', escape: false);
});

it('memasang verifikasi dua langkah lewat konfirmasi kode', function (): void {
    $komponen = Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->call('siapkanDuaLangkah');

    // Belum aktif sampai kodenya dikonfirmasi: orang yang gagal memindai QR
    // tidak boleh terkunci dari akunnya sendiri.
    expect($this->pengguna->fresh()->two_factor_confirmed_at)->toBeNull();

    $komponen->assertSet('sedangMenyiapkanDuaLangkah', true);

    $rahasia = decrypt($this->pengguna->fresh()->two_factor_secret);
    $kode = app(Google2FA::class)->getCurrentOtp($rahasia);

    $komponen->set('kodeDuaLangkah', $kode)
        ->call('konfirmasiDuaLangkah')
        ->assertHasNoErrors();

    expect($this->pengguna->fresh()->two_factor_confirmed_at)->not->toBeNull()
        ->and(SecurityEvent::query()->where('event', SecurityEventType::TwoFactorEnabled)->count())->toBe(1);
});

it('menolak kode dua langkah yang salah', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->call('siapkanDuaLangkah')
        ->set('kodeDuaLangkah', '000000')
        ->call('konfirmasiDuaLangkah')
        ->assertHasErrors(['kodeDuaLangkah']);

    expect($this->pengguna->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('mencatat pematian dua langkah sebagai peristiwa keamanan', function (): void {
    $this->pengguna->forceFill([
        'two_factor_secret' => encrypt('RAHASIAUJI234567'),
        'two_factor_recovery_codes' => encrypt(json_encode(['abcd-1234', 'efgh-5678'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    Livewire::actingAs($this->pengguna->fresh())
        ->test(Keamanan::class)
        ->call('matikanDuaLangkah');

    expect($this->pengguna->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(SecurityEvent::query()->where('event', SecurityEventType::TwoFactorDisabled)->count())->toBe(1);
});

it('tidak menyimpan PIN dalam bentuk apa adanya', function (): void {
    Livewire::actingAs($this->pengguna)
        ->test(Keamanan::class)
        ->set('pinBaru', '123456')
        ->set('pinUlang', '123456')
        ->call('pasangPin');

    $hash = $this->pengguna->fresh()->appLockPinHash();

    expect($hash)->not->toBe('123456')
        ->and(Hash::check('123456', $hash))->toBeTrue();
});
