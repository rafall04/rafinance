<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perangkat milik pengguna, bukan milik workspace: satu orang membuka beberapa
 * workspace dari ponsel yang sama. Karena itu tanpa RLS berbasis workspace —
 * penyaringannya lewat user_id di lapis aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent', 512)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
