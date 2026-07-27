<?php

declare(strict_types=1);
use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Kontras token warna
|--------------------------------------------------------------------------
|
| Rasio kontras adalah hal yang mudah dirusak tanpa sadar: seseorang menggeser
| satu warna dua digit heksadesimal supaya "lebih enak dilihat", dan teks yang
| tadinya lolos berubah jadi tidak terbaca bagi orang yang matanya tidak sebaik
| dia. Perubahan seperti itu tidak pernah terlihat di review.
|
| Berkas ini membaca token langsung dari app.css dan menghitung ulang rasionya.
| Dua nilai yang diuji: di atas --paper (latar halaman) dan di atas --paper-sunk
| (kartu), karena warna yang lolos di latar terang bisa gagal di kartu — persis
| yang pernah terjadi pada hijau dan kuning.
|
*/

/**
 * Luminansi relatif menurut WCAG 2.1.
 */
function luminansi(string $hex): float
{
    $hex = ltrim($hex, '#');
    $kanal = array_map(
        static function (int $v): float {
            $s = $v / 255;

            return $s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        },
        [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))],
    );

    return 0.2126 * $kanal[0] + 0.7152 * $kanal[1] + 0.0722 * $kanal[2];
}

function rasioKontras(string $depan, string $belakang): float
{
    $a = luminansi($depan);
    $b = luminansi($belakang);

    return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
}

/**
 * Membaca token dari blok tertentu di app.css.
 *
 * Sengaja membaca berkasnya, bukan menyalin nilainya ke test: nilai yang
 * disalin akan tetap hijau setelah seseorang mengubah CSS-nya.
 *
 * @return array<string, string>
 */
function tokenWarna(string $penanda): array
{
    $css = (string) file_get_contents(resource_path('css/app.css'));

    $mulai = strpos($css, $penanda);
    expect($mulai)->not->toBeFalse("Penanda blok '{$penanda}' tidak ditemukan di app.css.");

    $blok = substr($css, $mulai, 1400);
    preg_match_all('/(--[a-z0-9-]+):\s*(#[0-9a-fA-F]{6})/', $blok, $cocok, PREG_SET_ORDER);

    $token = [];
    foreach ($cocok as $baris) {
        $token[$baris[1]] ??= strtolower($baris[2]);
    }

    return $token;
}

it('menjaga kontras teks di mode terang', function (): void {
    $t = tokenWarna(':root {');

    $paper = $t['--paper'];
    $kartu = $t['--paper-sunk'];

    // Setiap warna yang pernah dipakai sebagai TEKS, di kedua latar.
    $teks = [
        '--ink' => 'teks utama',
        '--ink-soft' => 'teks sekunder',
        '--biru-50' => 'tautan dan aksi',
        '--hijau-20' => 'pemasukan dan pesan berhasil',
        '--merah-100' => 'pengeluaran dan galat',
        '--ungu-10' => 'transfer',
        '--kuning-teks' => 'peringatan',
    ];

    foreach ($teks as $token => $peran) {
        expect($t)->toHaveKey($token);

        expect(rasioKontras($t[$token], $paper))->toBeGreaterThanOrEqual(
            4.5,
            "{$token} ({$peran}) gagal WCAG AA di atas --paper.",
        );

        expect(rasioKontras($t[$token], $kartu))->toBeGreaterThanOrEqual(
            4.5,
            "{$token} ({$peran}) gagal WCAG AA di atas --paper-sunk. "
            .'Warna yang lolos di latar halaman bisa gagal di kartu.',
        );
    }
});

it('menjaga kontras teks di mode gelap', function (): void {
    $t = tokenWarna(":root[data-theme='dark']");

    $paper = $t['--paper'];
    $kartu = $t['--paper-sunk'];

    foreach (['--ink', '--ink-soft', '--biru-50', '--hijau-20', '--merah-100', '--ungu-10', '--kuning-teks'] as $token) {
        expect($t)->toHaveKey($token);

        expect(rasioKontras($t[$token], $paper))->toBeGreaterThanOrEqual(4.5, "{$token} gagal di --paper gelap.");
        expect(rasioKontras($t[$token], $kartu))->toBeGreaterThanOrEqual(4.5, "{$token} gagal di --paper-sunk gelap.");
    }
});

it('menyediakan kuning teks terpisah dari kuning bidang', function (): void {
    $terang = tokenWarna(':root {');

    // Kuning uang kertas memang terang — sebagai teks ia hanya ~2:1. Ia tetap
    // dipakai untuk bidang berwarna, dan --kuning-teks yang dipakai di atasnya.
    expect(rasioKontras($terang['--kuning-1'], $terang['--paper-sunk']))->toBeLessThan(
        4.5,
        'Kalau --kuning-1 sekarang lolos sebagai teks, --kuning-teks tidak lagi perlu ada.',
    );

    expect($terang['--kuning-teks'])->not->toBe($terang['--kuning-1']);
});

it('tidak memakai kuning bidang sebagai warna teks di markup', function (): void {
    // Penjaga yang sebenarnya: token boleh benar, tapi kalau ada yang menulis
    // text-kuning di Blade, hasilnya tetap tidak terbaca.
    $berkas = Finder::create()
        ->files()
        ->in(resource_path('views'))
        ->name('*.blade.php');

    foreach ($berkas as $satu) {
        $isi = (string) file_get_contents($satu->getRealPath());
        $nama = str_replace('\\', '/', $satu->getRelativePathname());

        expect(preg_match('/\btext-kuning\b(?!-teks)/', $isi))->toBe(
            0,
            "views/{$nama} memakai text-kuning sebagai warna teks (hanya ~2:1). Pakai text-kuning-teks.",
        );
    }
});

it('memakai ukuran teks minimal 12px di bilah navigasi', function (): void {
    // 11px terlalu kecil untuk label yang dibaca sekilas sambil berjalan.
    $isi = (string) file_get_contents(resource_path('views/components/tab-bar.blade.php'));

    expect($isi)->not->toContain('text-[11px]')
        ->and($isi)->toContain('text-[12px]');
});
