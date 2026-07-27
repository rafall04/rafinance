<?php

declare(strict_types=1);

use App\Support\Database\Rls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel (WS) pertama, dan sekaligus contoh pola untuk semua tabel workspace
 * di fase berikutnya: kolom workspace_id, index, lalu Rls::enableFor().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 16)->default('viewer');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Rls::enableFor('workspace_invites');
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invites');
    }
};
