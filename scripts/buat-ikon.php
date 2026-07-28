<?php

declare(strict_types=1);

/*
 * Set ikon final Rafin — opsi A: "Rp" duduk di garis buku.
 *
 * Empat peran, tiga aturan berbeda. Menghasilkan satu berkas lalu
 * memperkecilnya untuk semua peran adalah cara paling umum membuat ikon yang
 * salah di dua tempat sekaligus:
 *
 *   192 / 512      purpose "any" — bersudut bulat dengan sudut transparan.
 *                  Dialog pemasangan Chrome TIDAK membulatkannya sendiri, jadi
 *                  kalau kita tidak membulatkannya ia tampil sebagai kotak
 *                  tajam. Ini yang terlihat di tangkapan layar pemiliknya.
 *
 *   maskable-512   Penuh sampai tepi, isi dikecilkan ke zona aman. Peluncur
 *                  Android memotongnya jadi lingkaran, kotak bulat, atau
 *                  bentuk lain sesuai peranti — apa pun di luar lingkaran
 *                  80% tengah harus dianggap akan hilang. Berkas lama tidak
 *                  punya ruang aman sama sekali, jadi garis tegaknya terpotong.
 *
 *   apple-touch    Penuh sampai tepi dan HARUS legap. iOS membulatkannya
 *                  sendiri, dan piksel transparan di iOS menjadi hitam.
 *
 *   favicon.ico    32 px. Berkas sebelumnya berukuran nol byte.
 */

const SKALA = 4;

const AKAR = __DIR__.'/..';
const TUJUAN = AKAR.'/public/ikon';

const BIRU_TERANG = [0x3A, 0x97, 0xCB];
const BIRU_GELAP = [0x14, 0x4C, 0x6E];
const KERTAS = [0xFB, 0xFB, 0xF9];
const KUNING_TER = [0xE8, 0xC4, 0x4A];

/**
 * Huruf untuk "Rp".
 *
 * Public Sans milik aplikasi hanya tersedia sebagai woff2, dan GD tidak bisa
 * membacanya. Yang dipakai di sini huruf sans tebal dari sistem — tidak ada
 * yang akan membandingkan lengkung "R" di ikon peluncur dengan badan teks di
 * dalam aplikasi, dan hasilnya toh dibekukan jadi PNG yang ikut ter-commit.
 */
function font(): string
{
    $kandidat = [
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ];

    foreach ($kandidat as $berkas) {
        if (is_readable($berkas)) {
            return $berkas;
        }
    }

    fwrite(STDERR, "Tidak menemukan huruf sans tebal. Sunting daftar kandidat di fungsi font().\n");
    exit(1);
}

define('FONT', font());

function warna($img, array $rgb, int $alpha = 0)
{
    return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
}

function campur(array $a, array $b, float $t): array
{
    return [
        (int) round($a[0] + ($b[0] - $a[0]) * $t),
        (int) round($a[1] + ($b[1] - $a[1]) * $t),
        (int) round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

function kanvas(int $sisi, bool $legap)
{
    $img = imagecreatetruecolor($sisi, $sisi);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    if (! $legap) {
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    }

    imagealphablending($img, true);

    return $img;
}

function til($img, int $x, int $y, int $w, int $h, int $r, array $atas, array $bawah): void
{
    for ($i = 0; $i < $h; $i++) {
        $c = warna($img, campur($atas, $bawah, $i / max(1, $h - 1)));
        $dy = min($i, $h - 1 - $i);
        $potong = $dy < $r ? (int) round($r - sqrt(max(0, $r * $r - ($r - $dy) * ($r - $dy)))) : 0;

        imagefilledrectangle($img, $x + $potong, $y + $i, $x + $w - 1 - $potong, $y + $i, $c);
    }
}

/**
 * Menggambar ikonnya. Mengembalikan gambar berukuran $sisi.
 *
 * $ruangAman < 1 mengecilkan isinya ke tengah, untuk varian maskable.
 */
function gambar(int $sisi, float $radius, float $ruangAman, bool $legap)
{
    $img = kanvas($sisi, $legap);

    til($img, 0, 0, $sisi, $sisi, (int) ($sisi * $radius), BIRU_TERANG, BIRU_GELAP);

    $cx = (int) ($sisi / 2);
    $cy = (int) ($sisi * 0.44);
    $ukuran = (int) ($sisi * 0.40 * $ruangAman);

    $b = imagettfbbox($ukuran, 0, FONT, 'Rp');
    $lebar = $b[2] - $b[0];
    $tinggi = $b[1] - $b[7];
    $x = (int) round($cx - $lebar / 2 - $b[0]);
    $alas = (int) round($cy + $tinggi / 2 - $b[1]);

    imagettftext($img, $ukuran, 0, $x, $alas, warna($img, KERTAS), FONT, 'Rp');

    // Garis kertas di garis alas huruf. Panjang, supaya terbaca sebagai garis
    // pada kertas bergaris dan bukan sebagai garis bawah yang tertusuk ekor.
    $lw = (int) ($sisi * 0.62 * $ruangAman);
    $lh = max(1, (int) ($sisi * 0.040 * $ruangAman));
    til($img, (int) ($cx - $lw / 2), $alas, $lw, $lh, (int) ($lh / 2), KUNING_TER, KUNING_TER);

    return $img;
}

function simpan($besar, int $ukuran, string $berkas, bool $legap): void
{
    $kecil = kanvas($ukuran, $legap);
    imagealphablending($kecil, false);
    imagecopyresampled($kecil, $besar, 0, 0, 0, 0, $ukuran, $ukuran, imagesx($besar), imagesx($besar));
    imagesavealpha($kecil, true);
    imagepng($kecil, $berkas, 9);
    imagedestroy($kecil);
}

@mkdir(TUJUAN, 0777, true);

// purpose "any" — sudut bulat, sudut transparan.
$any = gambar(512 * SKALA, 0.22, 1.0, false);
simpan($any, 512, TUJUAN.'/512.png', false);
simpan($any, 192, TUJUAN.'/192.png', false);
simpan($any, 32, TUJUAN.'/favicon-32.png', false);
imagedestroy($any);

// maskable — penuh, isi di zona aman.
$maskable = gambar(512 * SKALA, 0.0, 0.72, true);
simpan($maskable, 512, TUJUAN.'/maskable-512.png', true);
imagedestroy($maskable);

// apple-touch — penuh dan legap.
$apple = gambar(180 * SKALA, 0.0, 1.0, true);
simpan($apple, 180, TUJUAN.'/apple-touch-icon.png', true);
imagedestroy($apple);

/*
 * favicon.ico berisi satu gambar PNG 32 px.
 *
 * Format ICO sejak Windows Vista memperbolehkan muatan PNG apa adanya, jadi
 * yang dibutuhkan hanya dua kepala berukuran tetap di depannya. GD tidak bisa
 * menulis ICO sendiri.
 */
$png = file_get_contents(TUJUAN.'/favicon-32.png');

$ico = pack('vvv', 0, 1, 1)              // reserved, tipe 1 (ikon), 1 gambar
    .pack('CCCC', 32, 32, 0, 0)          // lebar, tinggi, jumlah warna, reserved
    .pack('vv', 1, 32)                   // bidang warna, bit per piksel
    .pack('VV', strlen($png), 22)        // ukuran data, offset data
    .$png;

file_put_contents(AKAR.'/public/favicon.ico', $ico);
unlink(TUJUAN.'/favicon-32.png');

echo "selesai\n";
foreach (['192.png', '512.png', 'maskable-512.png', 'apple-touch-icon.png'] as $f) {
    $b = TUJUAN.'/'.$f;
    [$w, $h] = getimagesize($b);
    echo str_pad($f, 24), "{$w}x{$h}  ", filesize($b), " byte\n";
}
echo str_pad('favicon.ico', 24), filesize(AKAR.'/public/favicon.ico'), " byte\n";
