<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Isi dasar yang dibutuhkan aplikasi untuk berjalan.
 *
 * Hanya plan — tidak ada pengguna contoh, tidak ada transaksi karangan. Data
 * palsu di database pengembangan punya kebiasaan tidak enak: ia ikut terbawa ke
 * produksi lewat satu perintah yang dijalankan di terminal yang salah.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);
    }
}
