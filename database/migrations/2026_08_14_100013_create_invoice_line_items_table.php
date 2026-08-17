<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('weight_grams', 10, 3);
            $table->unsignedInteger('purity_id');
            $table->decimal('rate_per_gram', 12, 2);
            $table->decimal('metal_cost', 12, 2);
            $table->decimal('labour_cost', 12, 2)->default(0);
            $table->decimal('polish_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->foreign('invoice_id', 'fk_line_invoice')->references('id')->on('invoices');
            $table->foreign('item_id', 'fk_line_item')->references('id')->on('items');
            $table->foreign('transaction_id', 'fk_line_txn')->references('id')->on('transactions');
            $table->foreign('purity_id', 'fk_line_purity')->references('id')->on('purities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
