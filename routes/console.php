<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Jadwal
|--------------------------------------------------------------------------
|
| Semua dalam zona waktu Asia/Jakarta, bukan UTC. Aturan berulang yang disetel
| "setiap tanggal 1 pukul 06.00" harus jatuh pukul enam pagi menurut jam orang
| yang memakainya, bukan menurut jam server.
|
*/

Schedule::command('rafin:berulang')
    ->dailyAt('06:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();

// Partisi disiapkan jauh sebelum dibutuhkan. Partisi yang terlambat dibuat
// membuat baris jatuh ke DEFAULT, dan begitu ada baris di sana, partisi baru
// tidak bisa dilampirkan lagi.
Schedule::command('rafin:partitions --months=6 --prune')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Jakarta');

// Rantai audit diperiksa mingguan. Ia tidak mencegah apa pun — ia memberi tahu.
Schedule::command('rafin:audit:verify')
    ->weeklyOn(1, '03:00')
    ->timezone('Asia/Jakarta');
