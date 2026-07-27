<?php

declare(strict_types=1);

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Domain\Logging\SecurityLogger;
use App\Domain\Tenancy\Enums\SocialProvider;
use App\Domain\Tenancy\Exceptions\PenyambunganDitolak;
use App\Domain\Tenancy\Models\SocialAccount;
use App\Domain\Tenancy\Services\ResolveSocialUser;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
|--------------------------------------------------------------------------
| Masuk lewat penyedia pihak ketiga
|--------------------------------------------------------------------------
|
| Berkas ini ada terutama untuk satu skenario: pengambilalihan akun lewat surel
| yang tidak terverifikasi. Sisanya adalah kelengkapan.
|
| Penyedia dipalsukan di tingkat objek pengguna Socialite, bukan lewat HTTP:
| yang diuji adalah keputusan kita, bukan implementasi OAuth milik Google.
|
*/

function penggunaPenyedia(string $id, ?string $email, ?string $nama = 'Sri Rahayu'): SocialiteUser
{
    $satu = new SocialiteUser;

    $satu->map([
        'id' => $id,
        'name' => $nama,
        'email' => $email,
        'nickname' => null,
        'avatar' => 'https://contoh.test/foto.jpg',
    ]);

    return $satu;
}

function sambungkan(): ResolveSocialUser
{
    return app(ResolveSocialUser::class);
}

/*
|--------------------------------------------------------------------------
| Yang paling penting: anti-pengambilalihan
|--------------------------------------------------------------------------
*/

it('menolak menyambungkan otomatis kalau penyedia tidak memverifikasi surel', function (): void {
    // Korban sudah punya akun Rafin dengan kata sandi.
    User::factory()->create(['email' => 'sri@warung.test']);

    // Penyerang mendaftar di Facebook memakai surel yang sama. Facebook tidak
    // menjamin surelnya terverifikasi â€” menyambungkan otomatis berarti
    // menyerahkan buku kas korban.
    sambungkan()(SocialProvider::Facebook, penggunaPenyedia('fb-penyerang', 'sri@warung.test'));
})->throws(PenyambunganDitolak::class, 'Masuk dulu dengan kata sandi');

it('tidak membuat akun tersambung saat penyambungan ditolak', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    try {
        sambungkan()(SocialProvider::Facebook, penggunaPenyedia('fb-penyerang', 'sri@warung.test'));
    } catch (PenyambunganDitolak) {
        // memang disengaja
    }

    expect(SocialAccount::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

it('menyambungkan otomatis kalau penyedia menjamin surelnya terverifikasi', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);

    // Google memverifikasi surel sebelum akun bisa dipakai, jadi penyambungan
    // otomatis di sini aman.
    $hasil = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-123', 'sri@warung.test'));

    expect($hasil['user']->getKey())->toBe($sri->getKey())
        ->and($hasil['baru'])->toBeFalse()
        ->and($hasil['tersambung'])->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

it('mencatat penolakan sebagai peristiwa keamanan lewat HTTP', function (): void {
    config(['services.facebook.client_id' => 'uji', 'services.facebook.client_secret' => 'uji']);

    User::factory()->create(['email' => 'sri@warung.test']);

    Socialite::shouldReceive('driver->user')
        ->andReturn(penggunaPenyedia('fb-penyerang', 'sri@warung.test'));

    $this->get('/auth/facebook/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    expect(SecurityEvent::query()->where('event', SecurityEventType::OauthRejected)->count())->toBe(1)
        ->and(auth()->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Alur normal
|--------------------------------------------------------------------------
*/

it('membuat pengguna baru dari penyedia', function (): void {
    $hasil = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-baru', 'baru@warung.test'));

    $pengguna = $hasil['user'];

    expect($hasil['baru'])->toBeTrue()
        ->and($pengguna->email)->toBe('baru@warung.test')
        ->and($pengguna->name)->toBe('Sri Rahayu')
        ->and($pengguna->locale)->toBe('id')
        ->and($pengguna->timezone)->toBe('Asia/Jakarta')
        // Google sudah memverifikasi surelnya; memaksa konfirmasi ulang hanya
        // menambah langkah tanpa menambah keamanan.
        ->and($pengguna->email_verified_at)->not->toBeNull()
        ->and($pengguna->punyaKataSandi())->toBeFalse();
});

it('mengenali pengguna yang sama pada masuk berikutnya', function (): void {
    $pertama = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-tetap', 'sri@warung.test'));
    $kedua = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-tetap', 'sri@warung.test'));

    expect($kedua['user']->getKey())->toBe($pertama['user']->getKey())
        ->and($kedua['baru'])->toBeFalse()
        ->and(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('mengenali pengguna dari id penyedia meski surelnya berubah', function (): void {
    $pertama = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-tetap', 'lama@warung.test'));

    // Orang mengganti surel di Google. ID penyedialah yang mengikat, bukan
    // surelnya â€” kalau tidak, mengganti surel berarti kehilangan pembukuan.
    $kedua = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-tetap', 'baru@warung.test'));

    expect($kedua['user']->getKey())->toBe($pertama['user']->getKey())
        ->and(User::query()->count())->toBe(1);
});

it('menolak penyedia yang tidak memberikan surel', function (): void {
    // Surel wajib â€” ia satu-satunya jalan pulang kalau perangkat hilang.
    sambungkan()(SocialProvider::Apple, penggunaPenyedia('apple-tanpa-surel', null));
})->throws(PenyambunganDitolak::class, 'tidak memberikan alamat surel');

it('memakai bagian depan surel saat penyedia tidak mengirim nama', function (): void {
    // Apple hanya mengirim nama sekali, saat izin pertama diberikan.
    $hasil = sambungkan()(SocialProvider::Apple, penggunaPenyedia('apple-1', 'budi.santoso@warung.test', nama: null));

    expect($hasil['user']->name)->toBe('Budi Santoso');
});

it('menyeragamkan huruf besar-kecil surel', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);

    $hasil = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'SRI@Warung.Test'));

    expect($hasil['user']->getKey())->toBe($sri->getKey())
        ->and(User::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Penyambungan dari halaman Keamanan
|--------------------------------------------------------------------------
*/

it('menyambungkan penyedia ke pengguna yang sedang masuk', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);

    // Surel di Facebook berbeda, dan itu tidak masalah: yang membuktikan
    // kepemilikan di sini adalah sesi yang sudah ada, bukan surelnya.
    $hasil = sambungkan()(SocialProvider::Facebook, penggunaPenyedia('fb-1', 'lain@warung.test'), $sri);

    expect($hasil['user']->getKey())->toBe($sri->getKey())
        ->and($hasil['tersambung'])->toBeTrue()
        ->and(SecurityEvent::query()->where('event', SecurityEventType::OauthLinked)->count())->toBe(1);
});

it('menolak menyambungkan akun penyedia yang sudah dipakai orang lain', function (): void {
    $pemilik = User::factory()->create();
    SocialAccount::factory()->create([
        'user_id' => $pemilik->getKey(),
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'google-milik-orang-lain',
    ]);

    $penyerang = User::factory()->create();

    sambungkan()(SocialProvider::Google, penggunaPenyedia('google-milik-orang-lain', 'x@warung.test'), $penyerang);
})->throws(PenyambunganDitolak::class, 'sudah tersambung ke pengguna Rafin yang lain');

it('memutuskan sambungan penyedia', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);
    SocialAccount::factory()->create(['user_id' => $sri->getKey(), 'provider' => SocialProvider::Google]);

    sambungkan()->putuskan($sri, SocialProvider::Google);

    expect(SocialAccount::query()->count())->toBe(0)
        ->and(SecurityEvent::query()->where('event', SecurityEventType::OauthUnlinked)->count())->toBe(1);
});

it('menolak memutuskan satu-satunya cara masuk', function (): void {
    // Pengguna yang mendaftar lewat Google dan belum memasang kata sandi.
    $hasil = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'sri@warung.test'));

    sambungkan()->putuskan($hasil['user'], SocialProvider::Google);
})->throws(PenyambunganDitolak::class, 'satu-satunya cara Anda masuk');

it('mengizinkan memutuskan kalau masih ada kata sandi', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);
    SocialAccount::factory()->create(['user_id' => $sri->getKey(), 'provider' => SocialProvider::Google]);

    sambungkan()->putuskan($sri, SocialProvider::Google);

    expect(SocialAccount::query()->count())->toBe(0);
});

it('mengizinkan memutuskan kalau masih ada penyedia lain', function (): void {
    $hasil = sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'sri@warung.test'));
    $sri = $hasil['user'];

    sambungkan()(SocialProvider::Apple, penggunaPenyedia('apple-1', 'sri@warung.test'), $sri);

    sambungkan()->putuskan($sri, SocialProvider::Google);

    expect(SocialAccount::query()->where('user_id', $sri->getKey())->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Akun tanpa kata sandi
|--------------------------------------------------------------------------
*/

it('menolak masuk lewat formulir untuk akun tanpa kata sandi, tanpa galat 500', function (): void {
    // Keadaan yang sah: mendaftar lewat Google, belum memasang kata sandi.
    // Pemeriksa hash tidak boleh meledak saat menemukan kolom kosong.
    sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'sri@warung.test'));

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => 'tebakan'])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

it('menolak masuk dengan kata sandi kosong untuk akun tanpa kata sandi', function (): void {
    sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'sri@warung.test'));

    $this->post('/login', ['email' => 'sri@warung.test', 'password' => ''])
        ->assertSessionHasErrors();

    expect(auth()->check())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Rute dan konfigurasi
|--------------------------------------------------------------------------
*/

it('menjawab 404 untuk penyedia yang tidak dikenal', function (): void {
    $this->get('/auth/myspace/redirect')->assertNotFound();
});

it('menjawab 404 untuk penyedia yang belum dikonfigurasi', function (): void {
    // URL yang menjawab "penyedia itu ada tapi belum disetel" memberi tahu lebih
    // banyak daripada yang perlu diketahui siapa pun dari luar.
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    $this->get('/auth/google/redirect')->assertNotFound();
});

it('tidak menampilkan tombol penyedia yang belum dikonfigurasi', function (): void {
    config([
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
        'services.apple.client_id' => null,
        'services.apple.client_secret' => null,
        'services.facebook.client_id' => null,
        'services.facebook.client_secret' => null,
    ]);

    $this->get('/login')
        ->assertOk()
        ->assertDontSee('Masuk dengan Google');
});

it('menampilkan tombol penyedia yang sudah dikonfigurasi', function (): void {
    config(['services.google.client_id' => 'uji', 'services.google.client_secret' => 'uji']);

    $this->get('/login')->assertOk()->assertSee('Masuk dengan Google');
    $this->get('/register')->assertOk()->assertSee('Daftar dengan Google');
});

it('menyatakan penyedia mana yang memverifikasi surel', function (): void {
    expect(SocialProvider::Google->verifiesEmail())->toBeTrue()
        ->and(SocialProvider::Apple->verifiesEmail())->toBeTrue()
        // Facebook membiarkan akun dibuat dengan nomor telepon, dan surel yang
        // dikembalikannya tidak selalu terbukti milik orang yang sama.
        ->and(SocialProvider::Facebook->verifiesEmail())->toBeFalse();
});

it('tidak menyimpan nominal apa pun di peristiwa oauth', function (): void {
    $sri = User::factory()->create(['email' => 'sri@warung.test']);
    sambungkan()(SocialProvider::Google, penggunaPenyedia('google-1', 'sri@warung.test'), $sri);

    $peristiwa = SecurityEvent::query()->where('event', SecurityEventType::OauthLinked)->sole();

    // Aturan A6 tetap berlaku di jalur baru ini.
    foreach (array_keys($peristiwa->metadata ?? []) as $kunci) {
        expect(SecurityLogger::isForbiddenKey((string) $kunci))->toBeFalse();
    }
});
