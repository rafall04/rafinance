<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menjalankan migration lewat koneksi pemilik skema.
 *
 * `php artisan migrate` polos akan gagal, dan itu memang disengaja: koneksi
 * bawaan memakai role rafin_app yang tidak punya hak DDL sama sekali (aturan
 * A4). Pemisahan itu tidak ada gunanya kalau perintah sehari-hari boleh
 * menembusnya, jadi jalurnya dibuat eksplisit di sini.
 */
final class MigrateCommand extends Command
{
    protected $signature = 'rafin:migrate
        {--fresh : Hapus seluruh tabel lalu migrasi ulang dari nol}
        {--rollback : Mundurkan batch migration terakhir}
        {--seed : Jalankan seeder setelah migrasi}
        {--step= : Jumlah batch yang dimundurkan}
        {--force : Jalankan tanpa konfirmasi di produksi}
        {--pretend : Tampilkan SQL-nya saja, jangan dijalankan}';

    protected $description = 'Menjalankan migration lewat koneksi pemilik skema (rafin_owner)';

    public const CONNECTION = 'pgsql_migrate';

    public function handle(): int
    {
        $options = ['--database' => self::CONNECTION];

        foreach (['force', 'pretend'] as $flag) {
            if ($this->option($flag)) {
                $options['--'.$flag] = true;
            }
        }

        if ($this->option('rollback')) {
            if ($this->option('step') !== null) {
                $options['--step'] = $this->option('step');
            }

            return $this->call('migrate:rollback', $options);
        }

        $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

        if ($this->option('fresh')) {
            // Tabel jejak yang terpartisi tidak ikut terhapus oleh drop biasa
            // kalau masih ada partisi anak yang menggantung.
            $options['--drop-views'] = true;
        }

        $exitCode = $this->call($command, $options);

        if ($exitCode === self::SUCCESS && $this->option('seed')) {
            $seed = ['--database' => self::CONNECTION];

            if ($this->option('force')) {
                $seed['--force'] = true;
            }

            $exitCode = $this->call('db:seed', $seed);
        }

        return $exitCode;
    }
}
