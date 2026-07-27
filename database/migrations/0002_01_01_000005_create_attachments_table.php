<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran disimpan di disk privat; aksesnya hanya lewat signed URL berumur
 * pendek setelah kepemilikan diverifikasi (aturan A11). Tabel ini hanya
 * menyimpan penunjuknya, tidak pernah isinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('disk_path');
            $table->string('mime', 128);
            $table->unsignedBigInteger('size_bytes');

            // Dedup dan bukti keutuhan berkas.
            $table->string('sha256', 64);

            $table->foreignUlid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'transaction_id']);
            $table->index(['workspace_id', 'sha256']);
        });

        Rls::enableFor('attachments');
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
