<?php

declare(strict_types=1);

use App\Domain\Capture\Services\CaptureText;
use App\Domain\Ledger\Enums\TransactionSource;
use App\Domain\Ledger\Services\RecordOpeningBalance;
use App\Support\WaktuBuku;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/*
 * Tanggal buku menurut kalender pemiliknya.
 *
 * Seluruh berkas ini menguji satu selisih: aplikasi berjalan di UTC, sementara
 * penggunanya tidak. Untuk WIB selisihnya tujuh jam, artinya setiap catatan
 * antara tengah malam dan pukul tujuh pagi pernah jatuh ke tanggal kemarin —
 * dan di pergantian bulan, ke bulan yang salah. Laporan dan anggaran memakai
 * booked_date (aturan A10), jadi salah tanggal berarti salah laporan.
 */

/** 01:00 WIB tanggal 1 Agustus = 18:00 UTC tanggal 31 Juli. */
function dinihariWib(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-08-01 01:00:00', 'Asia/Jakarta');
}

function bekukanWaktu(CarbonImmutable $saat): void
{
    CarbonImmutable::setTestNow($saat);
    Carbon::setTestNow($saat);
}

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Carbon::setTestNow();
});

it('mencatat transaksi Telegram dini hari di tanggal setempat, bukan kemarin', function () {
    [$pengguna] = makeWorkspaceFor(attributes: ['timezone' => 'Asia/Jakarta']);
    buatAkun('Kas');

    bekukanWaktu(dinihariWib());

    $hasil = app(CaptureText::class)(
        teks: 'jajan 25rb',
        sumber: TransactionSource::Telegram,
        pengguna: $pengguna,
        currency: 'IDR',
    );

    expect($hasil->transaction)->not->toBeNull()
        ->and($hasil->transaction->booked_date->toDateString())->toBe('2026-08-01');
});

it('menghormati zona waktu masing-masing buku, bukan satu zona untuk semua', function () {
    // Jayapura UTC+9. Pukul 02:00 di sana masih 17:00 UTC hari sebelumnya.
    [$pengguna] = makeWorkspaceFor(attributes: ['timezone' => 'Asia/Jayapura']);
    buatAkun('Kas');

    bekukanWaktu(CarbonImmutable::parse('2026-08-01 02:00:00', 'Asia/Jayapura'));

    $hasil = app(CaptureText::class)(
        teks: 'kopi 15rb',
        sumber: TransactionSource::Telegram,
        pengguna: $pengguna,
        currency: 'IDR',
    );

    expect($hasil->transaction->booked_date->toDateString())->toBe('2026-08-01');
});

it('tetap memakai tanggal yang disebut pemanggil kalau memang disebut', function () {
    makeWorkspaceFor(attributes: ['timezone' => 'Asia/Jakarta']);
    $kas = buatAkun('Kas');

    bekukanWaktu(dinihariWib());

    $transaksi = catatPengeluaran(50_000, $kas, 'sewa', '2026-03-17');

    expect($transaksi->booked_date->toDateString())->toBe('2026-03-17');
});

it('menanggalkan saldo awal menurut zona waktu buku', function () {
    makeWorkspaceFor(attributes: ['timezone' => 'Asia/Jakarta']);
    $kas = buatAkun('Kas', 100_000);

    bekukanWaktu(dinihariWib());

    $transaksi = app(RecordOpeningBalance::class)($kas);

    expect($transaksi)->not->toBeNull()
        ->and($transaksi->booked_date->toDateString())->toBe('2026-08-01');
});

it('membaca zona dari workspace aktif dan jatuh ke bawaan tanpa konteks', function () {
    [, $workspace] = makeWorkspaceFor(attributes: ['timezone' => 'Asia/Jayapura']);

    expect(WaktuBuku::zona())->toBe('Asia/Jayapura');

    tenant()->clear();

    expect(WaktuBuku::zona())->toBe((string) config('rafin.default_timezone'));

    // Dikembalikan supaya pembersihan test berikutnya tidak kebingungan.
    tenant()->setWorkspace($workspace);
});
