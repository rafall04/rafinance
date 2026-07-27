<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bawaan workspace baru
    |--------------------------------------------------------------------------
    */

    /*
     * Koneksi yang dipakai memindai aturan berulang lintas workspace.
     *
     * Harus ber-BYPASSRLS: penjadwal berjalan tanpa konteks tenant, dan tanpa
     * itu tidak akan ada satu baris pun yang terlihat. Hanya diubah di test,
     * tempat seluruh data hidup di transaksi yang belum di-commit.
     */
    'recurring_scan_connection' => env('RAFIN_RECURRING_CONNECTION', 'pgsql_migrate'),

    'default_currency' => env('RAFIN_DEFAULT_CURRENCY', 'IDR'),
    'default_timezone' => env('RAFIN_DEFAULT_TIMEZONE', 'Asia/Jakarta'),
    'default_period_start_day' => 1,

    /*
    |--------------------------------------------------------------------------
    | Feature flag
    |--------------------------------------------------------------------------
    |
    | Aturan A12: tidak ada panggilan LLM di jalur input utama. Parser
    | rule-based dulu; LLM hanya cadangan, hanya untuk plan berbayar, dan untuk
    | sekarang dimatikan sepenuhnya. Alasannya bukan biaya semata — jalur input
    | utama harus punya waktu tanggap yang bisa diprediksi, sementara panggilan
    | jaringan ke penyedia pihak ketiga tidak punya itu.
    |
    */

    'features' => [
        'llm_parser' => env('RAFIN_FEATURE_LLM_PARSER', false),
        'ocr' => env('RAFIN_FEATURE_OCR', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas laju, per workspace dan bukan per IP
    |--------------------------------------------------------------------------
    |
    | Per IP tidak berarti banyak di sini: satu warung bisa punya tiga orang di
    | balik satu koneksi, dan satu penyalahguna bisa punya banyak IP.
    |
    */

    'rate_limits' => [
        'transactions_per_minute' => 60,
        'uploads_per_minute' => 10,
        'exports_per_hour' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retensi jejak, dalam bulan
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'security_events_months' => 12,
        'parse_failures_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Kanal Telegram (dipakai mulai FASE 2)
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'username' => env('TELEGRAM_BOT_USERNAME', 'rafinanceid_bot'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'link_code_ttl_minutes' => 10,
    ],

];
