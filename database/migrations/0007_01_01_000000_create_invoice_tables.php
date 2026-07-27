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
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('contact_id')->constrained()->restrictOnDelete();

            $table->string('number', 32);
            $table->date('issue_date');
            $table->date('due_date');

            // Total disimpan, bukan dihitung ulang dari item setiap kali:
            // tagihan yang sudah dikirim harus tetap menunjukkan angka yang
            // sama meski harga satuannya berubah kemudian.
            $table->bigInteger('total_minor');

            $table->string('status', 16)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['workspace_id', 'status', 'due_date']);
            $table->index(['workspace_id', 'contact_id']);
        });

        Rls::enableFor('invoices');

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();

            $table->string('description');

            // Kuantitas dalam per-seribu supaya 1,5 jam atau 0,25 kg tetap
            // bilangan bulat — alasan yang sama persis dengan aturan A1.
            $table->bigInteger('qty_milli')->default(1000);
            $table->bigInteger('unit_price_minor');

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id');
        });

        Rls::enableFor('invoice_items');

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained()->cascadeOnDelete();

            // Pembayaran tagihan SELALU punya transaksi di buku besar. Tanpa
            // itu, piutang bisa lunas tanpa uangnya pernah masuk ke akun mana
            // pun — dan neraca berbohong.
            $table->foreignUlid('transaction_id')->nullable()->constrained()->nullOnDelete();

            $table->bigInteger('amount_minor');
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index(['workspace_id', 'invoice_id']);
        });

        Rls::enableFor('invoice_payments');

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained()->cascadeOnDelete();

            $table->date('as_of_date');
            $table->bigInteger('system_balance_minor');
            $table->bigInteger('counted_balance_minor');
            $table->bigInteger('difference_minor');

            $table->foreignUlid('adjustment_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignUlid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'account_id', 'as_of_date']);
        });

        Rls::enableFor('reconciliations');
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
