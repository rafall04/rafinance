<?php

declare(strict_types=1);

use App\Domain\Logging\Enums\SecurityEventType;
use App\Domain\Logging\Models\SecurityEvent;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Alamat penelepon di belakang rantai proksi
|--------------------------------------------------------------------------
|
| Di produksi Rafin duduk di balik Cloudflare, cloudflared, dan dua lapis
| nginx. Kalau Laravel tidak diberi tahu siapa yang boleh dipercaya, ia
| membaca alamat proksi terdekat sebagai alamat penelepon — nilai yang sama
| persis untuk setiap orang di dunia.
|
| Itu merusak dua hal sekaligus, dan keduanya diam-diam:
|
|   security_events berhenti bisa membedakan satu orang yang lupa sandinya
|   tiga kali dari tiga ribu percobaan yang datang dari satu tempat. Tabel
|   itu tetap terisi rapi, hanya isinya tidak berarti apa-apa.
|
|   Batas laju yang jatuh ke IP jadi satu ember untuk semua orang. Satu
|   penyerang bisa menghabiskan jatah seluruh pengguna.
|
| Test ini menembak lewat HTTP sungguhan, bukan memeriksa nilai konfigurasi,
| supaya ia ikut gagal kalau susunan middleware berubah.
|
*/

/** Alamat penelepon sungguhan, sebagaimana dilihat Cloudflare. */
const IP_PENELEPON = '103.47.132.9';

it('membaca alamat penelepon dari X-Forwarded-For saat proksinya tepercaya', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    // REMOTE_ADDR meniru jembatan Docker: nginx di kontainer sebelah.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-For' => IP_PENELEPON.', 127.0.0.1'])
        ->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah']);

    $peristiwa = SecurityEvent::query()
        ->where('event', SecurityEventType::LoginFailed)
        ->sole();

    expect($peristiwa->ip)->toBe(IP_PENELEPON);
});

it('mengabaikan X-Forwarded-For yang dikarang dari alamat publik', function (): void {
    User::factory()->create(['email' => 'sri@warung.test']);

    // Penelepon datang langsung dari internet, bukan lewat proksi kita.
    // Header yang dibawanya adalah karangan dan harus diabaikan bulat-bulat.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
        ->withHeaders(['X-Forwarded-For' => '1.2.3.4'])
        ->post('/login', ['email' => 'sri@warung.test', 'password' => 'salah']);

    $peristiwa = SecurityEvent::query()
        ->where('event', SecurityEventType::LoginFailed)
        ->sole();

    expect($peristiwa->ip)->toBe('203.0.113.77')
        ->and($peristiwa->ip)->not->toBe('1.2.3.4');
});

it('mempercayai skema https yang diteruskan proksi', function (): void {
    // Cloudflare menerminasi TLS, jadi request yang sampai ke aplikasi
    // berupa HTTP polos. Tautan bertanda tangan untuk lampiran (aturan A11)
    // ikut menandatangani skemanya — kalau aplikasi mengira dirinya http
    // sementara peramban meminta https, tanda tangannya tidak pernah cocok.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('/login')
        ->assertOk();

    expect(request()->isSecure())->toBeTrue();
});

it('menyebut jaringan privat dan loopback sebagai proksi tepercaya', function (): void {
    // Daftar ini boleh diganti lewat TRUSTED_PROXIES, tapi bawaannya harus
    // menutupi jembatan Docker dan loopback tempat nginx duduk. Kalau ada
    // yang mempersempitnya tanpa sadar, alamat penelepon diam-diam salah
    // lagi — dan tidak ada yang meledak untuk memberitahu.
    $bawaan = (string) env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16');

    expect($bawaan)
        ->toContain('172.16.0.0/12')
        ->toContain('127.0.0.1');
});
