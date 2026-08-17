<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $table->unsignedTinyInteger('transaction_type_id');
            $table->enum('direction', ['IN', 'OUT']);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedTinyInteger('metal_type_id');
            $table->unsignedInteger('purity_id');
            $table->decimal('weight_grams', 10, 3);
            $table->decimal('rate_per_gram', 12, 2);
            $table->decimal('metal_cost', 12, 2);
            $table->decimal('labour_cost', 12, 2)->default(0);
            $table->decimal('polish_cost', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason', 255)->nullable();
            $table->decimal('deduction_percent_applied', 5, 2)->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->unsignedBigInteger('original_sale_transaction_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'credit', 'na'])->default('na');
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('party_id', 'fk_txn_party')->references('id')->on('parties');
            $table->foreign('transaction_type_id', 'fk_txn_type')->references('id')->on('transaction_types');
            $table->foreign('item_id', 'fk_txn_item')->references('id')->on('items');
            $table->foreign('metal_type_id', 'fk_txn_metal')->references('id')->on('metal_types');
            $table->foreign('purity_id', 'fk_txn_purity')->references('id')->on('purities');
            $table->foreign('original_sale_transaction_id', 'fk_txn_original_sale')->references('id')->on('transactions');
            $table->foreign('invoice_id', 'fk_txn_invoice')->references('id')->on('invoices');
            $table->foreign('created_by_user_id', 'fk_txn_created_by')->references('id')->on('users');
            $table->index('party_id', 'idx_txn_party');
            $table->index('transaction_date', 'idx_txn_date');
            $table->index(['transaction_type_id', 'direction'], 'idx_txn_type_direction');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
