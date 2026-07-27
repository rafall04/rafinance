<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Pengecualian CSRF
|--------------------------------------------------------------------------
|
| Test ini memanggil logika pencocokan middleware secara langsung, bukan lewat
| $this->post(). Itu disengaja, dan alasannya adalah inti dari test ini.
|
| PreventRequestForgery melewati seluruh pemeriksaan ketika runningUnitTests()
| bernilai benar:
|
|     if ($this->isReading($request) ||
|         $this->runningUnitTests() ||   ← selalu benar di dalam Pest
|         $this->inExceptArray($request) ||
|         ...
|
| Artinya request POST apa pun lolos di dalam test, dengan atau tanpa token,
| dengan atau tanpa pengecualian. Sebuah test yang menembak $this->post() ke
| /webhooks/telegram akan hijau bahkan kalau pengecualiannya dihapus — dan
| memang begitulah dua kanal Rafin sempat mati di produksi sementara 343 test
| melaporkan semuanya baik-baik saja.
|
| Yang diperiksa di sini karena itu adalah daftar pengecualiannya sendiri.
|
*/

/** Menanyakan langsung ke middleware apakah sebuah jalur dikecualikan. */
function dikecualikan(string $jalur): bool
{
    $middleware = app(PreventRequestForgery::class);

    $metode = new ReflectionMethod($middleware, 'inExceptArray');

    return (bool) $metode->invoke($middleware, Request::create($jalur, 'POST'));
}

it('mengecualikan webhook Telegram dari pemeriksaan CSRF', function (): void {
    // Server Telegram bukan peramban: ia tidak punya sesi dan tidak pernah
    // membawa token. Yang membuktikan keasliannya adalah header rahasia,
    // diperiksa di dalam controller.
    expect(dikecualikan('/webhooks/telegram'))->toBeTrue();
});

it('mengecualikan share target PWA dari pemeriksaan CSRF', function (): void {
    // POST-nya disusun sistem operasi saat seseorang membagikan struk dari
    // aplikasi lain. Spesifikasi Web Share Target tidak menyediakan tempat
    // untuk menitipkan token.
    expect(dikecualikan('/app/share'))->toBeTrue();
});

it('tidak mengecualikan jalur lain', function (string $jalur): void {
    // Pengecualian yang terlalu lebar jauh lebih berbahaya daripada tidak ada
    // pengecualian sama sekali: ia mematikan perlindungan di tempat yang
    // benar-benar membutuhkannya, tanpa gejala apa pun.
    expect(dikecualikan($jalur))->toBeFalse();
})->with([
    '/login',
    '/register',
    '/app/tambah',
    '/app/simpan',
    '/webhooks',
    '/app',
    '/',
]);
