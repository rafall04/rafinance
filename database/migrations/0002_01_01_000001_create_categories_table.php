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
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            // FK ke tabelnya sendiri ditambahkan terpisah di bawah. Laravel
            // memasang PRIMARY KEY sesudah seluruh foreign key, jadi referensi
            // ke diri sendiri di dalam Schema::create() akan gagal dengan
            // "no unique constraint matching given keys".
            $table->ulid('parent_id')->nullable();

            $table->string('name');
            $table->string('kind', 16);
            $table->string('color', 16)->nullable();
            $table->string('icon', 32)->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['workspace_id', 'kind', 'is_archived']);
            $table->index('parent_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        Rls::enableFor('categories');

        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('active');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->bigInteger('budget_minor')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        Rls::enableFor('projects');

        Schema::create('contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 16)->default('customer');
            $table->string('phone', 32)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'name']);
        });

        Rls::enableFor('contacts');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('categories');
    }
};
