<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\MigrateCommand;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Laravel 13 menggabungkan config/database.php milik aplikasi dengan
        // bawaan framework, jadi koneksi sqlite/mysql/sqlsrv tetap muncul meski
        // tidak ditulis di berkas kita. Keduanya dibuang di sini supaya
        // DB::connection('sqlite') melempar, bukan diam-diam bekerja.
        //
        // Bukan kerapian: trigger keseimbangan double-entry, larangan mengubah
        // transaksi posted, dan Row Level Security semuanya hidup di dalam
        // PostgreSQL. Satu test yang tanpa sengaja berjalan di atas SQLite akan
        // hijau tanpa menguji satu pun dari itu.
        config([
            'database.connections' => Arr::only(
                (array) config('database.connections'),
                ['pgsql', 'pgsql_migrate'],
            ),
        ]);
    }

    public function boot(): void
    {
        // Model domain tinggal di app/Domain/*/Models, sementara factory-nya
        // tetap di database/factories. Penebak bawaan Laravel hanya paham
        // App\Models, jadi ia diarahkan memakai nama kelas polos.
        Factory::guessFactoryNamesUsing(
            static fn (string $model): string => 'Database\\Factories\\'.class_basename($model).'Factory',
        );

        // Atribut yang belum ada di $fillable atau relasi yang belum dimuat
        // sebaiknya meledak saat dikembangkan, bukan diam-diam bernilai null
        // di laporan keuangan seseorang.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Tautan bertanda tangan untuk attachment (aturan A11) harus tetap sah
        // di belakang proksi HTTPS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        $this->tolakMigrasiLewatKoneksiAplikasi();
    }

    /**
     * `php artisan migrate` polos memakai koneksi bawaan, yaitu role rafin_app
     * yang memang tanpa hak DDL. Tanpa penjaga ini, hasilnya adalah galat
     * "permission denied for schema public" di tengah jalan — benar, tapi tidak
     * memberi tahu apa yang harus dilakukan.
     */
    private function tolakMigrasiLewatKoneksiAplikasi(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            $perintahMigrasi = ['migrate', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'];

            if (! in_array($event->command, $perintahMigrasi, true)) {
                return;
            }

            if ($event->input->getParameterOption('--database') === MigrateCommand::CONNECTION) {
                return;
            }

            throw new RuntimeException(
                'Migration harus lewat koneksi pemilik skema. Jalankan `php artisan rafin:migrate`'
                .($event->command === 'migrate:fresh' ? ' --fresh' : '')
                .'. Koneksi bawaan memakai role rafin_app yang sengaja tidak punya hak membuat '
                .'tabel, supaya Row Level Security benar-benar mengikat aplikasi (aturan A4).'
            );
        });
    }
}
