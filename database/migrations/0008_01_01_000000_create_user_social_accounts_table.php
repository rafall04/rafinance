<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun pihak ketiga yang tersambung ke pengguna Rafin.
 *
 * Tabel terpisah, bukan kolom `google_id` di users, karena satu orang wajar
 * punya beberapa: Google di ponsel, Apple di iPad. Menambah penyedia keempat
 * nanti tidak menuntut migrasi kolom.
 *
 * Milik pengguna, bukan workspace — jadi tanpa RLS berbasis tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_social_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 32);

            // ID pengguna di sisi penyedia. String, bukan integer: Apple
            // mengembalikan token buram yang panjang, bukan angka.
            $table->string('provider_user_id');

            // Surel apa adanya dari penyedia, disimpan untuk penelusuran kalau
            // nanti ada yang tidak cocok. Bukan sumber kebenaran — yang berlaku
            // tetap users.email.
            $table->string('provider_email')->nullable();
            $table->string('provider_nickname')->nullable();
            $table->string('avatar_url', 512)->nullable();

            // Apakah penyedia menyatakan surelnya terverifikasi SAAT
            // penyambungan. Disimpan supaya keputusan lama bisa ditelusuri
            // meski kebijakan penyedianya berubah kemudian.
            $table->boolean('email_verified_by_provider')->default(false);

            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Satu akun penyedia hanya boleh menunjuk ke satu pengguna Rafin.
            // Tanpa ini, dua orang bisa mengaku pemilik akun Google yang sama.
            $table->unique(['provider', 'provider_user_id']);

            // Dan satu pengguna hanya punya satu akun per penyedia.
            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_social_accounts');
    }
};
