<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 30)->unique();
            $table->unsignedBigInteger('party_id');
            $table->date('invoice_date');
            $table->decimal('total_metal_cost', 12, 2)->default(0);
            $table->decimal('total_labour_cost', 12, 2)->default(0);
            $table->decimal('total_polish_cost', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_exchange_deduction', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'credit'])->default('cash');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('party_id', 'fk_invoice_party')->references('id')->on('parties');
            $table->foreign('created_by_user_id', 'fk_invoice_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
