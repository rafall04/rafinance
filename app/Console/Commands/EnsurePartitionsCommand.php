<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Database\Partitions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menyiapkan partisi bulanan untuk tabel jejak dan membuang yang kedaluwarsa.
 *
 * Dijadwalkan bulanan. Kalau ia berhenti berjalan, baris menumpuk di partisi
 * DEFAULT — perintah ini melaporkannya sebagai peringatan, karena keadaan itu
 * menghalangi pembuatan partisi berikutnya.
 */
final class EnsurePartitionsCommand extends Command
{
    protected $signature = 'rafin:partitions
        {--months=6 : Berapa bulan ke depan yang disiapkan}
        {--prune : Buang partisi yang sudah lewat masa retensi}';

    protected $description = 'Menyiapkan partisi bulanan tabel jejak';

    /**
     * Retensi per tabel, dalam bulan. security_events 12 bulan sesuai
     * bagian 6 dokumen rancangan.
     */
    private const RETENTION_MONTHS = [
        'security_events' => 12,
    ];

    public function handle(): int
    {
        DB::setDefaultConnection(MigrateCommand::CONNECTION);

        foreach (array_keys(self::RETENTION_MONTHS) as $table) {
            $created = Partitions::ensureMonthly($table, now(), (int) $this->option('months'));

            $this->line($created === []
                ? "{$table}: partisi sudah lengkap"
                : "{$table}: dibuat ".implode(', ', $created));

            $stranded = Partitions::defaultPartitionCount($table);

            if ($stranded > 0) {
                $this->warn(
                    "{$table}: {$stranded} baris tersesat di partisi DEFAULT. "
                    .'Partisi terlambat dibuat — pindahkan baris ini sebelum partisi baru bisa dilampirkan.'
                );
            }

            if ($this->option('prune')) {
                $dropped = Partitions::dropMonthlyBefore(
                    $table,
                    now()->subMonths(self::RETENTION_MONTHS[$table]),
                );

                if ($dropped !== []) {
                    $this->line("{$table}: dibuang ".implode(', ', $dropped));
                }
            }
        }

        return self::SUCCESS;
    }
}
