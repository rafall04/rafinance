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
        Schema::create('transactions', function (Blueprint $table) {
            // ULID boleh datang dari client: antrean offline PWA membuatnya di
            // ponsel, lalu memakainya sekaligus sebagai Idempotency-Key.
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            // Tanggal buku, DATE di zona waktu pengguna — terpisah dari
            // created_at yang UTC (aturan A10). Laporan bulanan memakai ini.
            $table->date('booked_date');

            $table->string('description')->nullable();
            $table->string('kind', 16);

            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('contact_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status', 16)->default('draft');
            $table->string('source', 16)->default('web');
            $table->string('source_ref')->nullable();

            // Teks asli apa adanya. Sumber utama untuk memperbaiki parser, dan
            // satu-satunya cara membuktikan apa yang sebenarnya diketik orang.
            $table->text('raw_input')->nullable();

            // FK ke tabelnya sendiri ditambahkan terpisah di bawah, dengan
            // alasan yang sama seperti categories.parent_id.
            $table->ulid('reverses_transaction_id')->nullable();

            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'booked_date', 'created_at']);
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'project_id']);
            $table->index(['workspace_id', 'contact_id']);
            $table->index('reverses_transaction_id');
            $table->unique(['workspace_id', 'source', 'source_ref']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('reverses_transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });

        Rls::enableFor('transactions');

        $this->guardPostedTransactions();
        $this->guardLockedPeriods();
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $connection->statement('DROP FUNCTION IF EXISTS rafin_guard_posted_transaction() CASCADE');
        $connection->statement('DROP FUNCTION IF EXISTS rafin_check_period_lock() CASCADE');

        Schema::dropIfExists('transactions');
    }

    /**
     * Aturan A3 di tingkat database.
     *
     * Validasi aplikasi bisa dilewati lewat query mentah, job, atau perintah
     * artisan yang ditulis terburu-buru. Yang tidak bisa dilewati adalah ini.
     *
     * Satu-satunya perubahan yang diizinkan pada transaksi posted adalah
     * penandaan void, karena itulah separuh dari jalur koreksi yang benar:
     * buat transaksi pembalik, lalu tandai yang lama void.
     */
    private function guardPostedTransactions(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION rafin_guard_posted_transaction()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status = 'posted' THEN
                        RAISE EXCEPTION
                            'Transaksi posted tidak boleh dihapus. Buat transaksi pembalik, lalu tandai yang lama void.'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    RETURN OLD;
                END IF;

                IF OLD.status = 'posted' THEN
                    IF NEW.status <> 'void' THEN
                        RAISE EXCEPTION
                            'Transaksi posted tidak boleh diubah. Buat transaksi pembalik, lalu tandai yang lama void.'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    IF NEW.workspace_id IS DISTINCT FROM OLD.workspace_id
                        OR NEW.booked_date IS DISTINCT FROM OLD.booked_date
                        OR NEW.description IS DISTINCT FROM OLD.description
                        OR NEW.kind IS DISTINCT FROM OLD.kind
                        OR NEW.category_id IS DISTINCT FROM OLD.category_id
                        OR NEW.project_id IS DISTINCT FROM OLD.project_id
                        OR NEW.contact_id IS DISTINCT FROM OLD.contact_id
                        OR NEW.posted_at IS DISTINCT FROM OLD.posted_at
                        OR NEW.created_by IS DISTINCT FROM OLD.created_by
                    THEN
                        RAISE EXCEPTION
                            'Transaksi posted hanya boleh berubah status menjadi void, bukan isinya.'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        $connection->statement(<<<'SQL'
            CREATE TRIGGER transactions_guard_posted
                BEFORE UPDATE OR DELETE ON transactions
                FOR EACH ROW EXECUTE FUNCTION rafin_guard_posted_transaction()
        SQL);
    }

    /**
     * Periode yang sudah ditutup tidak menerima tulisan baru maupun perubahan.
     *
     * SECURITY DEFINER karena period_locks dilindungi RLS, dan trigger harus
     * bisa membacanya apa pun konteks tenant yang sedang aktif.
     */
    private function guardLockedPeriods(): void
    {
        $connection = Schema::getConnection();

        $connection->statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION rafin_check_period_lock()
            RETURNS trigger
            LANGUAGE plpgsql
            SECURITY DEFINER
            SET search_path = public, pg_temp
            AS $$
            DECLARE
                batas date;
            BEGIN
                SELECT MAX(locked_through) INTO batas
                  FROM period_locks
                 WHERE workspace_id = NEW.workspace_id
                   AND reopened_at IS NULL;

                IF batas IS NOT NULL AND NEW.booked_date <= batas THEN
                    RAISE EXCEPTION
                        'Periode sampai % sudah dikunci. Buka kembali periodenya kalau memang perlu diperbaiki.', batas
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        $connection->statement(<<<'SQL'
            CREATE TRIGGER transactions_check_period_lock
                BEFORE INSERT OR UPDATE ON transactions
                FOR EACH ROW EXECUTE FUNCTION rafin_check_period_lock()
        SQL);
    }
};
