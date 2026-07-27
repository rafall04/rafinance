<?php

declare(strict_types=1);

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Halaman depan
|--------------------------------------------------------------------------
|
| Halaman ini dibuka lebih dulu daripada apa pun yang lain, sering di jaringan
| yang buruk, oleh orang yang belum tentu jadi memakai Rafin. Yang diuji karena
| itu bukan hanya bahwa ia tampil, melainkan bahwa ia tetap ringan dan tetap
| jujur.
|
*/

it('menampilkan halaman depan kepada tamu', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Catat dulu.')
        ->assertSee('Rapikan nanti.')
        ->assertSee('Mulai gratis');
});

it('membawa yang sudah masuk langsung ke buku kasnya', function (): void {
    // Halaman pemasaran yang harus dilewati setiap hari adalah tol yang
    // dibayar justru oleh orang yang sudah terlanjur percaya.
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertRedirect('/app');
});

it('tidak memuat JavaScript apa pun', function (): void {
    // Anggaran JavaScript Rafin dibelanjakan untuk antrean offline, bukan
    // untuk halaman yang isinya teks. Kalau suatu saat ada yang menambahkan
    // bundel aplikasi ke sini, biar test ini yang memberi tahu.
    $html = (string) $this->get('/')->assertOk()->getContent();

    preg_match_all('/<script\b[^>]*\bsrc=[^>]*>/i', $html, $cocok);

    // Tag yang ditemukan ikut disebut. Pesan "diharapkan 0, dapat 1" memaksa
    // orang berikutnya mengulang seluruh penelusuran dari awal.
    expect($cocok[0])->toBe([], 'Halaman depan memuat skrip: '.implode(' | ', $cocok[0]));
});

it('menyebut dirinya beta dan gratis tanpa mengarang bukti', function (): void {
    $html = (string) $this->get('/')->getContent();

    expect($html)->toContain('beta');

    // Rafin belum punya pengguna. Testimoni atau jumlah pengguna di halaman
    // ini berarti karangan — dan untuk aplikasi keuangan, itu cara tercepat
    // kehilangan hal yang justru sedang dibangun.
    foreach (['testimoni', 'pengguna puas', 'juta pengguna', 'ribu pengguna'] as $karangan) {
        expect(mb_strtolower($html))->not->toContain($karangan);
    }
});

it('punya judul, deskripsi, dan gambar berbagi', function (): void {
    // Tautan Rafin paling sering dibagikan lewat WhatsApp. Tanpa tag ini yang
    // muncul di sana hanya URL telanjang.
    $this->get('/')
        ->assertSee('og:title', escape: false)
        ->assertSee('og:description', escape: false)
        ->assertSee('og:image', escape: false)
        ->assertSee('name="description"', escape: false);
});

it('menyediakan tautan lewati-ke-isi untuk pemakai keyboard', function (): void {
    $this->get('/')
        ->assertSee('Lewati ke isi')
        ->assertSee('id="utama"', escape: false);
});

it('menautkan ke halaman transparansi', function (): void {
    // Janji privasi yang tidak bisa diperiksa adalah pemasaran. Tautannya
    // harus benar-benar ada di halaman yang menyebut janji itu.
    $this->get('/')->assertSee(route('transparansi'), escape: false);

    $this->get('/transparansi')->assertOk();
});

it('tidak memasang animasi gulir pada elemen setinggi layar', function (): void {
    // Ini pernah terjadi dan tidak terlihat sebagai animasi yang salah,
    // melainkan sebagai halaman kosong.
    //
    // animation-timeline: view() dengan rentang `entry` mengukur perjalanan
    // elemen dari menyentuh tepi bawah viewport sampai masuk seluruhnya.
    // Elemen yang lebih tinggi daripada layar tidak pernah masuk seluruhnya,
    // jadi kemajuannya mandek dan ia bertahan di keadaan awal meski sudah
    // memenuhi layar. Bagian <section> hampir selalu setinggi itu.
    $html = (string) file_get_contents(resource_path('views/beranda.blade.php'));

    preg_match_all('/<section[^>]*class="[^"]*\bmuncul\b[^"]*"/i', $html, $cocok);

    expect($cocok[0])->toBe([], 'muncul dipasang pada <section>: '.implode(' | ', $cocok[0]));
});

it('tidak memakai sintaks penting Tailwind versi lama', function (): void {
    // Tailwind 4 memakai "!" sebagai akhiran (text-left!), bukan awalan.
    // Bentuk lama tidak menimbulkan galat apa pun — utilitasnya hanya diam
    // saja tidak berlaku, dan itu ketahuan jauh belakangan.
    foreach (['views/beranda.blade.php', 'views/components/layouts/publik.blade.php'] as $berkas) {
        $isi = (string) file_get_contents(resource_path($berkas));

        expect(preg_match('/class="[^"]*(?:^|\s)![a-z\[]/i', $isi))->toBe(
            0,
            "{$berkas} memakai awalan ! milik Tailwind 3. Versi 4 memakai akhiran.",
        );
    }
});

it('menjelaskan cara kerja tanpa sinyal di halaman depan', function (): void {
    // Ini pertanyaan pertama yang muncul soal Rafin, dan jawabannya tidak
    // boleh bersembunyi di dokumentasi. Termasuk bagian yang paling sering
    // disalahpahami: bahwa tidak ada yang perlu dipasang lebih dulu.
    $this->get('/')
        ->assertOk()
        ->assertSee('Tanpa sinyal')
        ->assertSee('Tidak perlu memasang apa pun.');
});

it('tidak memakai id yang sama dua kali', function (string $jalur): void {
    // ID ganda memutus kaitan aria-labelledby tanpa menimbulkan galat apa pun:
    // halamannya tampil normal, hanya pembaca layar mengumumkan judul yang
    // salah untuk sebuah bagian. Ini pernah terjadi saat dua bagian sama-sama
    // memakai id="manfaat".
    $html = (string) $this->get($jalur)->assertOk()->getContent();

    preg_match_all('/\sid="([^"]+)"/i', $html, $cocok);
    $ganda = array_keys(array_filter(array_count_values($cocok[1]), fn (int $n): bool => $n > 1));

    expect($ganda)->toBe([], "{$jalur} memakai id ganda: ".implode(', ', $ganda));
})->with(['/', '/transparansi']);

it('menulis halaman transparansi tanpa jargon internal', function (): void {
    // Halaman itu dibaca orang yang sedang menimbang apakah aman menaruh
    // catatan uangnya di sini. Istilah yang datang dari dalam kepala pembuatnya
    // menambah ragu, bukan mengurangi.
    $html = mb_strtolower((string) $this->get('/transparansi')->assertOk()->getContent());

    // Bagian utama harus bebas istilah ini. Yang teknis boleh muncul, tapi
    // hanya di dalam <details> yang harus dibuka sendiri oleh pembacanya.
    $utama = mb_substr($html, 0, (int) mb_strpos($html, '<details'));

    foreach (['panel admin', 'row level security', 'tabel transaksi', 'rilis', 'query'] as $jargon) {
        expect($utama)->not->toContain($jargon);
    }
});

it('memakai satu urutan judul tanpa melompat', function (): void {
    // h1 lalu langsung h3 membuat pembaca layar mengumumkan struktur yang
    // salah, dan itu tidak terlihat sama sekali dari tampilannya.
    $html = (string) $this->get('/')->getContent();

    preg_match_all('/<h([1-6])\b/i', $html, $cocok);
    $tingkat = array_map('intval', $cocok[1]);

    expect($tingkat[0])->toBe(1);

    $sebelumnya = 1;
    foreach ($tingkat as $t) {
        expect($t - $sebelumnya)->toBeLessThanOrEqual(1);
        $sebelumnya = max($sebelumnya, $t);
    }
});
