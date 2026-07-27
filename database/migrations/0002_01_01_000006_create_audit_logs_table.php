<?php

declare(strict_types=1);

use App\Support\Database\Partitions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit milik workspace — berisi nominal, dan justru karena itu
 * dilindungi RLS dan tidak pernah bisa dibaca admin platform (aturan A5).
 *
 * Dipasangkan rantai hash: hash = sha256(prev_hash || action || auditable_id ||
 * created_at). Gunanya bukan mencegah perubahan — hak DDL saja sudah tidak
 * dimiliki role aplikasi — tapi membuat perubahan itu KETAHUAN. Seseorang yang
 * menghapus atau menyunting satu baris akan memutus rantainya, dan
 * `rafin:audit:verify` menunjuk persis di baris mana putusnya.
 *
 * Dipartisi per bulan seperti security_events, dengan konsekuensi yang sama:
 * primary key harus memuat kolom partisi, jadi kuncinya (id, created_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE TABLE audit_logs (
                id              char(26)     NOT NULL,
                workspace_id    char(26)     NOT NULL,
                actor_user_id   char(26)     NULL,
                action          varchar(64)  NOT NULL,
                auditable_type  varchar(128) NULL,
                auditable_id    char(26)     NULL,
                before          jsonb        NULL,
                after           jsonb        NULL,
                ip              varchar(45)  NULL,
                prev_hash       char(64)     NULL,
                hash            char(64)     NOT NULL,
                created_at      timestamp(6) NOT NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        $connection->statement('CREATE INDEX audit_logs_workspace_idx ON audit_logs (workspace_id, created_at DESC)');
        $connection->statement('CREATE INDEX audit_logs_auditable_idx ON audit_logs (workspace_id, auditable_type, auditable_id)');
        $connection->statement('CREATE INDEX audit_logs_action_idx ON audit_logs (workspace_id, action, created_at DESC)');

        $connection->statement('CREATE TABLE audit_logs_default PARTITION OF audit_logs DEFAULT');

        Partitions::ensureMonthly('audit_logs', now()->subMonths(2), 14, appendOnly: true);

        $this->applyRls();
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP TABLE IF EXISTS audit_logs CASCADE');
    }

    /**
     * RLS ditulis tangan dan bukan lewat Rls::enableFor() karena tabel ini
     * hanya boleh ditambah, tidak pernah diubah maupun dihapus. Tidak ada
     * policy UPDATE dan tidak ada policy DELETE — tanpa policy, PostgreSQL
     * menolak operasinya, dan rantai hash tidak bisa dirapikan diam-diam.
     */
    private function applyRls(): void
    {
        $connection = Schema::getConnection();

        $connection->statement('ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY');
        $connection->statement('ALTER TABLE audit_logs FORCE ROW LEVEL SECURITY');

        $connection->statement(<<<'SQL'
            CREATE POLICY audit_logs_read ON audit_logs
                FOR SELECT
                USING (workspace_id = current_setting('app.workspace_id', true))
        SQL);

        $connection->statement(<<<'SQL'
            CREATE POLICY audit_logs_append ON audit_logs
                FOR INSERT
                WITH CHECK (workspace_id = current_setting('app.workspace_id', true))
        SQL);

        $role = config('database.connections.pgsql.username');

        if (is_string($role) && preg_match('/^[a-z_][a-z0-9_]*$/i', $role) === 1) {
            $connection->statement("GRANT SELECT, INSERT ON audit_logs TO {$role}");
        }

        // Hak UPDATE dan DELETE dicabut pada induk maupun setiap partisi.
        // Memberi GRANT saja tidak cukup: ALTER DEFAULT PRIVILEGES di level
        // database sudah terlanjur memberikan DML penuh untuk setiap tabel baru.
        Partitions::revokeMutation('audit_logs');

        foreach (Partitions::childrenOf('audit_logs') as $partisi) {
            Partitions::revokeMutation($partisi);
        }
    }
};
