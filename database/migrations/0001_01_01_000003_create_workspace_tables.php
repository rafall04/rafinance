<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workspace dan keanggotaannya dibuat dalam satu migration karena policy RLS
 * tabel workspaces perlu membaca workspace_members — keduanya harus sudah ada
 * sebelum policy bisa dipasang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('type', 16)->default('personal');
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency', 3)->default('IDR');

            // Awal periode pembukuan. Banyak usaha kecil menutup buku ikut
            // tanggal gajian atau tanggal tagihan, bukan tanggal 1.
            $table->unsignedTinyInteger('period_start_day')->default(1);
            $table->string('timezone', 64)->default('Asia/Jakarta');

            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16)->default('viewer');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
            $table->index('user_id');
        });

        $this->createMembershipFunction();
        $this->applyWorkspacePolicies();
        $this->applyMemberPolicies();
    }

    public function down(): void
    {
        Schema::getConnection()->statement('DROP FUNCTION IF EXISTS rafin_is_member(text, text)');

        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
    }

    /**
     * Policy RLS yang menyebut tabel lain akan ikut memicu policy tabel itu,
     * dan workspaces ↔ workspace_members saling menunjuk. Fungsi SECURITY
     * DEFINER milik rafin_owner (yang ber-BYPASSRLS) memutus lingkaran itu.
     *
     * search_path dikunci di dalam fungsi: SECURITY DEFINER dengan search_path
     * yang bisa diubah pemanggil adalah lubang keamanan klasik PostgreSQL.
     */
    private function createMembershipFunction(): void
    {
        $connection = Schema::getConnection();
        $appRole = config('database.connections.pgsql.username');

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION rafin_is_member(workspace text, member text)
            RETURNS boolean
            LANGUAGE sql
            STABLE
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
                SELECT EXISTS (
                    SELECT 1 FROM workspace_members m
                    WHERE m.workspace_id = workspace
                      AND m.user_id = member
                      AND member <> ''
                );
            $$;
        SQL);

        $connection->statement('REVOKE ALL ON FUNCTION rafin_is_member(text, text) FROM PUBLIC');

        if (is_string($appRole) && preg_match('/^[a-z_][a-z0-9_]*$/i', $appRole) === 1) {
            $connection->statement("GRANT EXECUTE ON FUNCTION rafin_is_member(text, text) TO {$appRole}");
        }
    }

    /**
     * workspaces bukan tabel (WS) — ia tidak punya kolom workspace_id, ia
     * adalah tenant-nya sendiri. Penyaringannya berdasarkan keanggotaan.
     */
    private function applyWorkspacePolicies(): void
    {
        $connection = Schema::getConnection();

        $connection->statement('ALTER TABLE workspaces ENABLE ROW LEVEL SECURITY');
        $connection->statement('ALTER TABLE workspaces FORCE ROW LEVEL SECURITY');

        $connection->statement(<<<'SQL'
            CREATE POLICY workspaces_read ON workspaces
                FOR SELECT
                USING (
                    id = current_setting('app.workspace_id', true)
                    OR rafin_is_member(id, current_setting('app.user_id', true))
                )
        SQL);

        // Siapa pun yang sudah masuk boleh membuat workspace, tapi hanya atas
        // namanya sendiri. Batas jumlahnya urusan kuota plan, bukan RLS.
        $connection->statement(<<<'SQL'
            CREATE POLICY workspaces_create ON workspaces
                FOR INSERT
                WITH CHECK (owner_id = current_setting('app.user_id', true))
        SQL);

        $connection->statement(<<<'SQL'
            CREATE POLICY workspaces_modify ON workspaces
                FOR UPDATE
                USING (rafin_is_member(id, current_setting('app.user_id', true)))
                WITH CHECK (rafin_is_member(id, current_setting('app.user_id', true)))
        SQL);

        $connection->statement(<<<'SQL'
            CREATE POLICY workspaces_remove ON workspaces
                FOR DELETE
                USING (owner_id = current_setting('app.user_id', true))
        SQL);

        Rls::grantAppAccess('workspaces');
    }

    /**
     * Keanggotaan disaring dua arah: baris milik diri sendiri (supaya daftar
     * "workspace saya" bisa dibaca sebelum ada tenant aktif), dan baris milik
     * workspace yang sedang aktif (supaya daftar anggota bisa dibaca).
     */
    private function applyMemberPolicies(): void
    {
        $connection = Schema::getConnection();

        $connection->statement('ALTER TABLE workspace_members ENABLE ROW LEVEL SECURITY');
        $connection->statement('ALTER TABLE workspace_members FORCE ROW LEVEL SECURITY');

        $connection->statement(<<<'SQL'
            CREATE POLICY workspace_members_read ON workspace_members
                FOR SELECT
                USING (
                    (user_id = current_setting('app.user_id', true) AND current_setting('app.user_id', true) <> '')
                    OR (workspace_id = current_setting('app.workspace_id', true) AND current_setting('app.workspace_id', true) <> '')
                )
        SQL);

        // Saat onboarding, workspace baru belum jadi tenant aktif, jadi baris
        // pertama lolos lewat cabang "untuk diri sendiri".
        $connection->statement(<<<'SQL'
            CREATE POLICY workspace_members_create ON workspace_members
                FOR INSERT
                WITH CHECK (
                    (user_id = current_setting('app.user_id', true) AND current_setting('app.user_id', true) <> '')
                    OR (workspace_id = current_setting('app.workspace_id', true) AND current_setting('app.workspace_id', true) <> '')
                )
        SQL);

        $connection->statement(<<<'SQL'
            CREATE POLICY workspace_members_modify ON workspace_members
                FOR UPDATE
                USING (workspace_id = current_setting('app.workspace_id', true))
                WITH CHECK (workspace_id = current_setting('app.workspace_id', true))
        SQL);

        $connection->statement(<<<'SQL'
            CREATE POLICY workspace_members_remove ON workspace_members
                FOR DELETE
                USING (workspace_id = current_setting('app.workspace_id', true))
        SQL);

        Rls::grantAppAccess('workspace_members');
    }
};
