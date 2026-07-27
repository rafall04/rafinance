<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penguncian periode pembukuan.
 *
 * Dibuat sebelum tabel transactions karena trigger periode terkunci membaca
 * tabel ini — urutannya tidak bisa dibalik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_locks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            $table->date('locked_through');
            $table->foreignUlid('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at');

            // Membuka kembali periode adalah peristiwa yang harus terlihat,
            // bukan penghapusan baris. Riwayatnya tetap ada.
            $table->foreignUlid('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'reopened_at', 'locked_through']);
        });

        Rls::enableFor('period_locks');
    }

    public function down(): void
    {
        Schema::dropIfExists('period_locks');
    }
};
