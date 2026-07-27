<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastruktur langganan.
 *
 * Semua plan berharga nol saat ini, dan itu bukan alasan menunda tabelnya:
 * kuota, penghitung pemakaian, dan penegakannya harus sudah berjalan sejak
 * awal. Menambahkan batas pada sistem yang sudah dipakai orang jauh lebih sulit
 * daripada melonggarkannya.
 *
 * TIDAK memakai RLS berbasis workspace aktif: admin platform memang boleh
 * melihat plan, status langganan, dan pembayaran. Yang tidak boleh dilihatnya
 * adalah isi bukunya, dan itu ada di tabel lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 32)->unique();
            $table->string('name');

            // Nol untuk semuanya selama beta. Tetap BIGINT minor unit (A1).
            $table->bigInteger('price_minor')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('interval', 16)->default('monthly');
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->jsonb('limits');
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('plan_id')->constrained();
            $table->string('status', 16)->default('active');
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref')->nullable();
            $table->timestamps();

            $table->unique('workspace_id');
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('usage_counters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            // metric: transactions, attachments_bytes, members, ocr_calls
            $table->string('metric', 32);

            // period_key: '2026-07' untuk bulanan, 'total' untuk yang tidak
            // pernah disetel ulang.
            $table->string('period_key', 16);
            $table->bigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'metric', 'period_key']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('IDR');
            $table->string('status', 16)->default('pending');
            $table->string('provider', 32)->nullable();
            $table->string('provider_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'paid_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Admin platform. Sengaja kolom sederhana, bukan sistem peran:
            // hanya ada dua keadaan, dan sistem peran yang lengkap justru
            // membuat batasnya lebih mudah dilanggar tanpa sengaja.
            $table->boolean('is_platform_admin')->default(false);
            $table->timestamp('deletion_requested_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_platform_admin', 'deletion_requested_at']);
        });

        Schema::dropIfExists('payments');
        Schema::dropIfExists('usage_counters');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
