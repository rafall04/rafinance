<?php

declare(strict_types=1);

use App\Support\Database\Partitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak keamanan — metadata saja, tanpa nominal (aturan A6).
 *
 * Dipartisi per bulan sejak awal. Bukan karena datanya sudah banyak hari ini,
 * tapi karena mengubah tabel besar menjadi terpartisi belakangan menuntut
 * downtime, sementara memulainya terpartisi tidak menuntut apa-apa. Retensinya
 * 12 bulan, dan menghapus partisi jauh lebih murah daripada DELETE massal.
 *
 * Konsekuensi partisi: primary key wajib memuat kolom partisi, jadi kuncinya
 * (id, created_at) dan bukan id saja. ULID tetap unik secara praktis.
 *
 * TIDAK memakai RLS berbasis workspace: tabel ini justru yang boleh dibaca
 * admin platform. Itu sebabnya larangan nominalnya ditegakkan berlapis — arch
 * test, test pemindai isi, dan penjaga saat penulisan di SecurityLogger.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE TABLE security_events (
                id            char(26)      NOT NULL,
                user_id       char(26)      NULL,
                workspace_id  char(26)      NULL,
                event         varchar(64)   NOT NULL,
                ip            varchar(45)   NULL,
                user_agent    varchar(512)  NULL,
                geo_country   char(2)       NULL,
                geo_city      varchar(128)  NULL,
                device_id     varchar(255)  NULL,
                metadata      jsonb         NULL,
                created_at    timestamp(0)  NOT NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        $connection->statement('CREATE INDEX security_events_user_idx ON security_events (user_id, created_at DESC)');
        $connection->statement('CREATE INDEX security_events_workspace_idx ON security_events (workspace_id, created_at DESC)');
        $connection->statement('CREATE INDEX security_events_event_idx ON security_events (event, created_at DESC)');

        // Jaring pengaman: baris yang jatuh di luar semua partisi tetap
        // tersimpan, tidak ditolak. Peristiwa keamanan yang hilang karena
        // partisinya lupa dibuat adalah kegagalan yang jauh lebih mahal.
        $connection->statement('CREATE TABLE security_events_default PARTITION OF security_events DEFAULT');

        Partitions::ensureMonthly('security_events', now()->subMonths(2), 14);
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP TABLE IF EXISTS security_events CASCADE');
    }
};
