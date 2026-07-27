<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\Schema;

/**
 * Row Level Security PostgreSQL untuk tabel bertanda (WS) — aturan A4.
 *
 * Global scope Eloquent adalah lapis pertama; ini lapis kedua. Keduanya ada
 * bukan karena berlebihan, tapi karena lapis pertama bisa dilewati tanpa
 * sengaja: satu DB::table(), satu relasi yang lupa di-scope, satu query mentah
 * di laporan. Kalau itu terjadi, database yang menolak, bukan reviewer.
 *
 * Tiga hal yang membuatnya benar-benar mengikat:
 *
 *   1. FORCE ROW LEVEL SECURITY — tanpa ini, pemilik tabel kebal policy.
 *   2. Role aplikasi (rafin_app) bukan pemilik tabel dan tanpa BYPASSRLS.
 *   3. current_setting(..., true) mengembalikan NULL kalau konteks tenant
 *      belum diset, dan `workspace_id = NULL` bernilai NULL — bukan true.
 *      Artinya lupa memasang konteks berarti tidak melihat apa-apa, bukan
 *      melihat semuanya.
 */
final class Rls
{
    public const SETTING = 'app.workspace_id';

    /**
     * Menyalakan RLS pada satu tabel workspace.
     */
    public static function enableFor(string $table, string $column = 'workspace_id'): void
    {
        $connection = Schema::getConnection();
        $policy = "{$table}_tenant_isolation";
        $setting = self::SETTING;

        $connection->statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        $connection->statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");

        $connection->statement(<<<SQL
            CREATE POLICY {$policy} ON {$table}
                USING ({$column} = current_setting('{$setting}', true))
                WITH CHECK ({$column} = current_setting('{$setting}', true))
        SQL);

        self::grantAppAccess($table);
    }

    /**
     * Mematikan RLS — hanya dipakai di down() migration.
     */
    public static function disableFor(string $table): void
    {
        $connection = Schema::getConnection();
        $policy = "{$table}_tenant_isolation";

        $connection->statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        $connection->statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        $connection->statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /**
     * Memberi role aplikasi hak DML — tapi tidak pernah DDL.
     *
     * ALTER DEFAULT PRIVILEGES di level database seharusnya sudah menangani
     * ini, tapi hak akses adalah hal yang lebih baik dinyatakan dua kali
     * daripada diasumsikan sekali.
     */
    public static function grantAppAccess(string $table): void
    {
        $connection = Schema::getConnection();
        $role = self::appRole();

        if ($role === null) {
            return;
        }

        $connection->statement("GRANT SELECT, INSERT, UPDATE, DELETE ON {$table} TO {$role}");
    }

    /**
     * Nama role aplikasi diambil dari konfigurasi koneksi, bukan ditulis
     * tetap, supaya staging dan produksi boleh memakai nama lain.
     */
    private static function appRole(): ?string
    {
        $role = config('database.connections.pgsql.username');

        if (! is_string($role) || preg_match('/^[a-z_][a-z0-9_]*$/i', $role) !== 1) {
            return null;
        }

        return $role;
    }
}
