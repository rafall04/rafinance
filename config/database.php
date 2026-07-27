<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Koneksi bawaan
    |--------------------------------------------------------------------------
    |
    | Rafin hanya mendukung PostgreSQL. Trigger keseimbangan double-entry,
    | larangan ubah transaksi posted, dan Row Level Security semuanya hidup di
    | dalam database — engine lain akan diam-diam melewatkan semua itu, jadi
    | koneksi sqlite/mysql sengaja tidak disediakan sama sekali.
    |
    */

    'default' => env('DB_CONNECTION', 'pgsql'),

    /*
    |--------------------------------------------------------------------------
    | Koneksi database
    |--------------------------------------------------------------------------
    |
    | Dua koneksi ke database yang sama dengan role berbeda (aturan A4):
    |
    |   pgsql          role rafin_app. Bukan pemilik tabel, tanpa BYPASSRLS,
    |                  tanpa hak DDL. Semua lalu lintas aplikasi lewat sini,
    |                  sehingga RLS benar-benar mengikat.
    |
    |   pgsql_migrate  role rafin_owner. Pemilik skema, BYPASSRLS, hanya
    |                  dipakai migration dan perintah skema.
    |
    | Konsekuensi yang disengaja: `php artisan migrate` polos akan GAGAL dengan
    | permission denied, karena rafin_app memang tidak boleh membuat tabel.
    | Pakai `php artisan rafin:migrate` yang mengarah ke koneksi yang benar.
    |
    */

    'connections' => [

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5433'),
            'database' => env('DB_DATABASE', 'rafin'),
            'username' => env('DB_USERNAME', 'rafin_app'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'pgsql_migrate' => [
            'driver' => 'pgsql',
            'url' => env('DB_MIGRATE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5433'),
            'database' => env('DB_DATABASE', 'rafin'),
            'username' => env('DB_MIGRATE_USERNAME', 'rafin_owner'),
            'password' => env('DB_MIGRATE_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tabel migration
    |--------------------------------------------------------------------------
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis
    |--------------------------------------------------------------------------
    |
    | Belum dipakai di dev Windows: Horizon mensyaratkan ext-pcntl yang tidak
    | tersedia di Windows, jadi queue dan cache memakai driver database. Blok
    | ini tetap ditulis supaya deploy produksi (Linux) tinggal mengganti
    | QUEUE_CONNECTION dan CACHE_STORE menjadi redis. Klien default predis
    | (PHP murni) supaya tidak menuntut ekstensi phpredis.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'predis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'rafin'), '_').'_database_'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
