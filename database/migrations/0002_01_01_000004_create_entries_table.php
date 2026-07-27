<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->restrictOnDelete();

            // Bertanda: debit positif, kredit negatif. Jumlah seluruh entries
            // dalam satu transaksi wajib nol (aturan A2).
            $table->bigInteger('amount_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'account_id']);
            $table->index('transaction_id');
        });

        Rls::enableFor('entries');

        $this->enforceBalance();
        $this->guardPostedEntries();
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $connection->statement('DROP FUNCTION IF EXISTS rafin_check_entry_balance() CASCADE');
        $connection->statement('DROP FUNCTION IF EXISTS rafin_guard_posted_entries() CASCADE');

        Schema::dropIfExists('entries');
    }

    /**
     * Aturan A2 — pembukuan double-entry, ditegakkan database.
     *
     * Constraint trigger yang DEFERRABLE INITIALLY DEFERRED: pemeriksaannya
     * ditunda sampai commit, karena entries sebuah transaksi disisipkan satu
     * per satu dan tidak pernah seimbang di tengah jalan.
     *
     * Konsekuensi yang harus diingat saat menulis kode: pemeriksaan baru
     * terjadi saat COMMIT. Service PostTransaction memanggil
     * `SET CONSTRAINTS ALL IMMEDIATE` sebelum commit supaya kesalahan muncul di
     * tempat yang bisa dijelaskan ke pengguna, bukan sebagai kegagalan commit
     * yang tidak jelas asalnya.
     */
    private function enforceBalance(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION rafin_check_entry_balance()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            DECLARE
                sasaran text;
                jumlah bigint;
                banyak integer;
            BEGIN
                sasaran := COALESCE(NEW.transaction_id, OLD.transaction_id);

                SELECT COALESCE(SUM(amount_minor), 0), COUNT(*)
                  INTO jumlah, banyak
                  FROM entries
                 WHERE transaction_id = sasaran;

                -- Transaksi yang seluruh entries-nya sudah dihapus tidak perlu
                -- diperiksa; yang menjaganya adalah aturan transaksi posted.
                IF banyak = 0 THEN
                    RETURN NULL;
                END IF;

                IF jumlah <> 0 THEN
                    RAISE EXCEPTION
                        'Transaksi tidak seimbang. Total debit dan kredit harus sama. Selisih %.', jumlah
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NULL;
            END;
            $$;
        SQL);

        $connection->statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER entries_must_balance
                AFTER INSERT OR UPDATE OR DELETE ON entries
                DEFERRABLE INITIALLY DEFERRED
                FOR EACH ROW EXECUTE FUNCTION rafin_check_entry_balance()
        SQL);
    }

    /**
     * Entries milik transaksi posted terkunci sepenuhnya (aturan A3).
     *
     * Tanpa ini, larangan mengubah transaksi bisa dilangkahi cukup dengan
     * mengubah angka di entries-nya — dan saldo berubah tanpa satu pun jejak
     * di header transaksi.
     */
    private function guardPostedEntries(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION rafin_guard_posted_entries()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            DECLARE
                keadaan text;
                sasaran text;
            BEGIN
                sasaran := COALESCE(NEW.transaction_id, OLD.transaction_id);

                SELECT status INTO keadaan FROM transactions WHERE id = sasaran;

                IF keadaan = 'posted' THEN
                    RAISE EXCEPTION
                        'Entries milik transaksi posted tidak boleh diubah. Buat transaksi pembalik.'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        $connection->statement(<<<'SQL'
            CREATE TRIGGER entries_guard_posted
                BEFORE INSERT OR UPDATE OR DELETE ON entries
                FOR EACH ROW EXECUTE FUNCTION rafin_guard_posted_entries()
        SQL);
    }
};
