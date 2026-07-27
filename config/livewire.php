<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Livewire
|--------------------------------------------------------------------------
|
| Sengaja hanya memuat kunci yang menyimpang dari bawaan paket. Livewire
| memanggil mergeConfigFrom(), jadi sisanya tetap terisi sendiri — dan berkas
| pendek seperti ini menunjukkan apa yang benar-benar diputuskan, bukan
| menyembunyikannya di antara dua ratus baris salinan.
|
*/

return [

    /*
     * Livewire menyuntikkan skripnya sendiri ke setiap jawaban HTML yang
     * dilihatnya. Untuk Rafin itu keliru di dua sisi.
     *
     * Kedua layout yang benar-benar memakai Livewire — app dan tamu — sudah
     * memanggil @livewireScriptConfig sendiri, jadi injeksinya cuma menambah
     * satu jalur kedua yang melakukan hal sama.
     *
     * Dan halaman depan tidak memakai Livewire sama sekali. Membiarkan
     * injeksi menyala berarti mengirim livewire.js ke orang yang baru membuka
     * alamat Rafin untuk pertama kali, sering di jaringan yang buruk, untuk
     * halaman yang isinya teks. Kuota itu dibayar oleh orang yang belum tentu
     * jadi memakai Rafin.
     *
     * Efek sampingnya: perilakunya jadi pasti. Sebelum ini penyuntikan
     * bergantung pada apakah ada sesuatu yang lebih dulu menyentuh Livewire di
     * dalam proses yang sama — yang membuat test halaman depan lulus sendirian
     * lalu gagal di dalam suite penuh.
     */
    'inject_assets' => false,

];
