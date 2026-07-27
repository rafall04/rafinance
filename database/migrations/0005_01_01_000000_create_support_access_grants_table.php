<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izin akses dukungan.
 *
 * Admin platform TIDAK BISA impersonate. Kalau seseorang butuh bantuan yang
 * menuntut melihat datanya, dialah yang menerbitkan izin — berdurasi paling
 * lama 24 jam, tercatat, dan pemilik buku diberi tahu saat izin itu dipakai.
 *
 * Tabel ini sengaja TIDAK memakai RLS berbasis workspace aktif: admin platform
 * harus bisa membaca daftarnya untuk tahu izin apa yang berlaku, dan isinya
 * memang hanya metadata — siapa memberi izin kepada siapa, sampai kapan, untuk
 * lingkup apa. Tidak ada nominal di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('granted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('admin_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('scope', 32)->default('read_metadata');
            $table->text('reason')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'expires_at']);
            $table->index(['admin_user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
    }
};
