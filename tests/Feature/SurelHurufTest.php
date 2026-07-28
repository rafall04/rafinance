<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;

/*
 * Surel sebagai identitas, tanpa peduli huruf besar-kecil.
 *
 * PostgreSQL membandingkan varchar secara peka huruf, jadi "Budi@Gmail.com"
 * dan "budi@gmail.com" adalah dua nilai berbeda bagi index unik sekalipun
 * keduanya kotak surel yang sama. Satu-satunya yang menjaga agar keduanya
 * tidak pernah berdampingan adalah kebiasaan menormalkan sebelum menulis —
 * dan kebiasaan tidak berlaku untuk kode yang ditulis besok.
 */

it('menyimpan surel huruf kecil meski diketik dengan huruf besar saat daftar', function () {
    $balasan = $this->post('/register', [
        'name' => 'Budi',
        'email' => 'Budi@Gmail.Com',
        'password' => 'kata-sandi-yang-panjang',
        'password_confirmation' => 'kata-sandi-yang-panjang',
    ]);

    $balasan->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'budi@gmail.com')->exists())->toBeTrue();
});

it('menerima huruf besar-kecil apa pun saat masuk', function () {
    User::factory()->create([
        'email' => 'budi@gmail.com',
        'password' => Hash::make('kata-sandi-yang-panjang'),
    ]);

    $this->post('/login', [
        'email' => 'BUDI@Gmail.Com',
        'password' => 'kata-sandi-yang-panjang',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('menolak dua akun untuk satu kotak surel yang sama, apa pun hurufnya', function () {
    User::factory()->create(['email' => 'budi@gmail.com']);

    // Ditulis lewat model, bukan lewat HTTP: yang diuji di sini justru jaring
    // pengaman terakhirnya. Fortify memang menormalkan di pintu depan, tapi
    // pintu depan bukan satu-satunya jalan menulis ke tabel users — ada panel
    // admin, seeder, perintah artisan, dan kode yang belum ditulis.
    expect(fn () => User::query()->create([
        'name' => 'Budi lagi',
        'email' => 'BUDI@GMAIL.COM',
        'password' => Hash::make('apa-saja-yang-penting-panjang'),
        'locale' => 'id',
        'timezone' => 'Asia/Jakarta',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('menormalkan surel di model, bukan hanya di pengendali Fortify', function () {
    $pengguna = User::factory()->create(['email' => '  Siti@Contoh.TEST  ']);

    expect($pengguna->refresh()->email)->toBe('siti@contoh.test');
});
