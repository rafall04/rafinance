<?php

declare(strict_types=1);

use App\Http\Middleware\PastikanAplikasiTerbuka;
use App\Livewire\App\KunciAplikasi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Kunci aplikasi yang benar-benar mengunci.
 *
 * Sebelumnya seluruhnya ditegakkan JavaScript, jadi mengetik alamat halaman
 * langsung sudah cukup untuk melewatinya. Berkas ini menguji dari sisi server:
 * tanpa PIN yang benar di sesi ini, tidak ada halaman /app yang terbuka.
 */

function penggunaBerpin(string $pin = '123456'): User
{
    [$pengguna] = makeWorkspaceFor();

    $pengguna->forceFill(['app_lock_pin_hash' => Hash::make($pin)])->save();

    return $pengguna;
}

it('menolak halaman aplikasi saat terkunci, meski alamatnya diketik langsung', function () {
    $pengguna = penggunaBerpin();

    $this->actingAs($pengguna)
        ->get('/app/laporan')
        ->assertRedirect(route('app.kunci'));
});

it('membiarkan halaman kunci sendiri tetap terbuka', function () {
    $pengguna = penggunaBerpin();

    $this->actingAs($pengguna)->get('/app/kunci')->assertOk();
});

it('membuka seluruh halaman setelah PIN yang benar dimasukkan', function () {
    $pengguna = penggunaBerpin('654321');

    $this->actingAs($pengguna);

    Livewire::actingAs($pengguna)
        ->test(KunciAplikasi::class)
        ->set('pin', '654321')
        ->call('buka')
        ->assertHasNoErrors();

    $this->get('/app/laporan')->assertOk();
});

it('tidak membuka apa pun kalau PIN-nya salah', function () {
    $pengguna = penggunaBerpin('654321');

    Livewire::actingAs($pengguna)
        ->test(KunciAplikasi::class)
        ->set('pin', '000000')
        ->call('buka')
        ->assertHasErrors('pin');

    $this->actingAs($pengguna)->get('/app/laporan')->assertRedirect(route('app.kunci'));
});

it('mengunci kembali setelah jendela diamnya lewat', function () {
    $pengguna = penggunaBerpin();

    $this->actingAs($pengguna);
    session()->put(PastikanAplikasiTerbuka::SESSION_KEY, time());

    $this->get('/app/laporan')->assertOk();

    // Digeser mundur melewati batas diam, seolah ponselnya ditinggalkan.
    $lewat = time() - ((int) config('rafin.app_lock_idle_minutes') * 60) - 30;
    session()->put(PastikanAplikasiTerbuka::SESSION_KEY, $lewat);

    $this->get('/app/laporan')->assertRedirect(route('app.kunci'));
});

it('tidak mengganggu pengguna yang tidak memasang PIN sama sekali', function () {
    [$pengguna] = makeWorkspaceFor();

    $this->actingAs($pengguna)->get('/app/laporan')->assertOk();
});

it('menjawab 423 dan bukan pengalihan untuk permintaan latar', function () {
    $pengguna = penggunaBerpin();

    $this->actingAs($pengguna)
        ->getJson('/app/laporan')
        ->assertStatus(423);
});
