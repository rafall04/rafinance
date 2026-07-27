<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel penangkapan input — jantung dari prinsip "capture dulu, klasifikasi
 * belakangan".
 *
 * inbox_items menampung apa pun yang masuk, lengkap atau tidak. parse_failures
 * menyimpan yang gagal dibaca parser, dan sengaja TIDAK dibersihkan otomatis:
 * ia sumber utama untuk memperbaiki parser, dan parser yang tidak pernah
 * membaik akan pelan-pelan mengusir penggunanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('source', 16);
            $table->text('raw_text')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->string('media_path')->nullable();

            $table->string('parse_status', 16)->default('pending');
            $table->jsonb('parsed_draft')->nullable();

            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Pesan Telegram yang perlu diperbarui saat item diselesaikan dari
            // web. Tombol basi membuat sistem terasa rusak.
            $table->unsignedBigInteger('telegram_chat_id')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'parse_status', 'created_at']);
        });

        Rls::enableFor('inbox_items');

        Schema::create('parse_failures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('raw_text');
            $table->string('reason', 128);
            $table->foreignUlid('inbox_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'resolved_at']);
        });

        Rls::enableFor('parse_failures');

        Schema::create('input_aliases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            // Pintasan buatan pengguna: "bbm" => Transportasi + Kas.
            $table->string('keyword', 64);
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'keyword']);
        });

        Rls::enableFor('input_aliases');

        Schema::create('transaction_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('label', 64);
            $table->jsonb('payload');
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'use_count']);
        });

        Rls::enableFor('transaction_templates');
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_templates');
        Schema::dropIfExists('input_aliases');
        Schema::dropIfExists('parse_failures');
        Schema::dropIfExists('inbox_items');
    }
};
