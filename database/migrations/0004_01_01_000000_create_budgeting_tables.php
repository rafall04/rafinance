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
        Schema::create('budgets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained()->cascadeOnDelete();

            $table->string('period', 16)->default('monthly');
            $table->bigInteger('amount_minor');

            // Sisa anggaran bulan ini ikut ke bulan depan. Tanpa ini, orang
            // yang berhemat di bulan pendek merasa dihukum saat bulan panjang.
            $table->boolean('rollover')->default(false);

            $table->date('starts_on');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'category_id', 'period']);
            $table->index(['workspace_id', 'is_active']);
        });

        Rls::enableFor('budgets');

        Schema::create('budget_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('budget_id')->constrained()->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('allocated_minor');
            $table->bigInteger('carried_in_minor')->default(0);

            // Cache, dihitung ulang oleh job. Ditandai terang-terangan supaya
            // tidak ada yang memperlakukannya sebagai sumber kebenaran —
            // sumbernya selalu entries.
            $table->bigInteger('spent_minor')->default(0);
            $table->timestamp('recalculated_at')->nullable();

            $table->timestamps();

            $table->unique(['budget_id', 'period_start']);
            $table->index(['workspace_id', 'period_start']);
        });

        Rls::enableFor('budget_periods');

        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('target_minor');
            $table->foreignUlid('account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('target_date')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'achieved_at']);
        });

        Rls::enableFor('goals');

        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->jsonb('template');
            $table->string('frequency', 16)->default('monthly');
            $table->unsignedTinyInteger('day_of_period')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active', 'next_run_at']);
        });

        Rls::enableFor('recurring_rules');
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('budget_periods');
        Schema::dropIfExists('budgets');
    }
};
