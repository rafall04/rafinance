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
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Lima tipe akuntansi baku. `subtype` yang membedakan Kas dari BCA
            // dari GoPay — semuanya sama-sama aset, tapi tampil beda di UI.
            $table->string('type', 16);
            $table->string('subtype', 16)->default('other');

            $table->string('currency', 3)->default('IDR');

            // BIGINT minor unit, bukan decimal (aturan A1).
            $table->bigInteger('opening_balance_minor')->default(0);

            $table->string('color', 16)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);

            // Tambahan di luar daftar kolom dokumen rancangan, dan alasannya:
            // pembukuan double-entry menuntut dua akun untuk setiap transaksi,
            // sementara pengguna hanya memilih SATU (kas atau bank) lalu memilih
            // kategori. Sisi lawannya adalah akun sistem "Beban", "Pendapatan",
            // atau "Modal awal" yang dibuat otomatis dan tidak pernah muncul di
            // daftar akun pengguna. Kategori tetap yang dipakai untuk pelaporan.
            $table->boolean('is_system')->default(false);

            $table->timestamps();

            $table->index(['workspace_id', 'type']);
            $table->index(['workspace_id', 'is_archived', 'sort_order']);
            $table->index(['workspace_id', 'is_system']);
        });

        Rls::enableFor('accounts');
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
