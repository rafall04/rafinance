<?php

declare(strict_types=1);

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('menampilkan halaman masuk dalam bahasa Indonesia', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Masuk')
        ->assertSee('Kata sandi')
        ->assertDontSee('Password', escape: false);
});

it('mendaftarkan pengguna baru dengan bawaan Indonesia', function (): void {
    $this->post('/register', [
        'name' => 'Sri Rahayu',
        'email' => 'sri@warung.test',
        'password' => 'sandi-yang-panjang',
        'password_confirmation' => 'sandi-yang-panjang',
    ])->assertRedirect();

    $user = User::query()->where('email', 'sri@warung.test')->sole();

    expect($user->locale)->toBe('id')
        ->and($user->timezone)->toBe('Asia/Jakarta')
        ->and($user->getKey())->toHaveLength(26);
});

it('menolak surel yang sudah terdaftar dengan pesan yang menolong', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/register', [
        'name' => 'Sri Lain',
        'email' => 'sri@warung.test',
        'password' => 'sandi-yang-panjang',
        'password_confirmation' => 'sandi-yang-panjang',
    ])->assertSessionHasErrors(['email' => 'Surel ini sudah terdaftar. Masuk saja, atau setel ulang kata sandinya.']);
});

it('mencatat login.success lewat listener, bukan dari controller', function (): void {
    $user = User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $event = SecurityEvent::query()->where('event', SecurityEventType::LoginSuccess)->sole();

    expect($event->user_id)->toBe($user->getKey())
        ->and($event->metadata)->toHaveKey('guard');
});

it('mencatat login.failed tanpa pernah menyimpan kata sandinya', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah-sekali']);

    $event = SecurityEvent::query()->where('event', SecurityEventType::LoginFailed)->sole();

    expect($event->metadata)->toHaveKey('attempted_email', 'sri@warung.test');

    // Isi seluruh baris dipindai, bukan hanya kunci yang kita duga ada.
    $mentah = DB::connection('pgsql')->selectOne(
        'SELECT metadata::text AS teks FROM security_events WHERE id = ?',
        [$event->getKey()],
    );

    expect(strtolower((string) $mentah->teks))->not->toContain('salah-sekali');
});

it('memberi pesan yang sama untuk surel salah dan kata sandi salah', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $sandiSalah = $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah']);
    $surelTidakAda = $this->post('/login', ['email' => 'tidak-ada@warung.test', 'password' => 'salah']);

    // Pesan yang berbeda akan mengubah halaman masuk jadi alat pemeriksa
    // apakah sebuah alamat surel terdaftar di Rafin.
    $sandiSalah->assertSessionHasErrors(['email' => 'Surel atau kata sandi tidak cocok.']);
    $surelTidakAda->assertSessionHasErrors(['email' => 'Surel atau kata sandi tidak cocok.']);
});

it('mencatat logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect();

    expect(SecurityEvent::query()->where('event', SecurityEventType::Logout)->count())->toBe(1);
});

it('memutar ulang id sesi saat masuk', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    $this->get('/login');
    $sebelum = session()->getId();

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'password']);

    expect(session()->getId())->not->toBe($sebelum);
});

it('membatasi percobaan masuk beruntun', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    foreach (range(1, 5) as $percobaan) {
        $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah']);
    }

    // Batas terlampaui dijawab di halaman masuk, bukan dengan layar 429.
    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah'])
        ->assertRedirect()
        ->assertSessionHasErrorsIn('default', ['email']);

    expect(session('errors')->getBag('default')->first('email'))
        ->toContain('Terlalu banyak percobaan');
});

it('menahan pengguna yang belum memverifikasi surel', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/app')->assertRedirect('/email/verify');
});

it('tidak menyisakan konteks tenant setelah request selesai', function (): void {
    [$user, $workspace] = makeWorkspaceFor();

    $this->actingAs($user)->get('/app')->assertOk();

    // Middleware membersihkan konteks di terminate(); tanpa itu, worker atau
    // request berikutnya di proses yang sama bisa mewarisi tenant ini.
    expect(tenant()->id())->toBeNull()
        ->and($workspace->getKey())->not->toBeNull();
});
