<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * RefreshDatabase yang menghormati pemisahan dua role.
 *
 * Bawaan Laravel menjalankan migration lewat koneksi bawaan. Di Rafin koneksi
 * bawaan adalah rafin_app, yang memang tidak boleh membuat tabel — jadi
 * migration harus lewat rafin_owner, sementara test-nya sendiri tetap berjalan
 * sebagai rafin_app.
 *
 * Itu bukan kerepotan yang bisa dihindari: kalau test berjalan sebagai
 * rafin_owner, seluruh policy RLS akan dilewati diam-diam dan setiap test
 * isolasi tenant akan hijau tanpa membuktikan apa pun.
 */
trait RefreshRafinDatabase
{
    use RefreshDatabase {
        refreshTestDatabase as private laravelRefreshTestDatabase;
    }

    protected function refreshTestDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', [
                '--database' => 'pgsql_migrate',
                '--drop-views' => true,
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Transaksi hanya dibuka di koneksi aplikasi. Koneksi migration sudah
     * selesai tugasnya sebelum test pertama berjalan.
     *
     * @return array<int, string>
     */
    protected function connectionsToTransact(): array
    {
        return ['pgsql'];
    }
}
