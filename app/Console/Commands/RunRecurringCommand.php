<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Budgeting\Services\RunRecurringRules;
use Illuminate\Console\Command;

/**
 * Menjalankan aturan berulang yang jatuh tempo. Dijadwalkan harian.
 */
final class RunRecurringCommand extends Command
{
    protected $signature = 'rafin:berulang';

    protected $description = 'Menjalankan aturan transaksi berulang yang sudah jatuh tempo';

    public function handle(RunRecurringRules $jalankan): int
    {
        $hasil = $jalankan();

        $this->components->info("Aturan dijalankan: {$hasil['dijalankan']}");

        if ($hasil['gagal'] > 0) {
            // Bukan kegagalan perintah: aturan lain tetap berjalan, dan itu
            // memang perilaku yang diinginkan. Tapi harus terlihat.
            $this->components->warn("Aturan gagal: {$hasil['gagal']}. Periksa log untuk rinciannya.");
        }

        return self::SUCCESS;
    }
}
