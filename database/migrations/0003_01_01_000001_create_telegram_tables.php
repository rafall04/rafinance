<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel kanal Telegram. Milik pengguna, bukan workspace — satu akun Telegram
 * bisa berpindah antar buku lewat /switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            // Unik: satu akun Telegram hanya boleh terhubung ke satu pengguna.
            $table->unsignedBigInteger('telegram_user_id')->unique();
            $table->unsignedBigInteger('chat_id');
            $table->foreignUlid('active_workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('username', 64)->nullable();
            $table->timestamp('linked_at');
            $table->timestamp('unlinked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('telegram_link_codes', function (Blueprint $table) {
            // Kode enam digit yang dibuat dari web dan kedaluwarsa sepuluh
            // menit. Satu-satunya jalan menghubungkan akun — telegram_user_id
            // mentah dari webhook tidak pernah dipercaya.
            $table->string('code', 6)->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('telegram_updates', function (Blueprint $table) {
            // update_id sebagai primary key: inilah dedup-nya (aturan A9).
            // Telegram mengirim ulang update yang belum di-ack, dan tanpa ini
            // satu gangguan jaringan berarti pengeluaran tercatat dua kali.
            $table->unsignedBigInteger('update_id')->primary();
            $table->jsonb('payload');
            $table->string('status', 16)->default('received');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('telegram_link_codes');
        Schema::dropIfExists('telegram_links');
    }
};
