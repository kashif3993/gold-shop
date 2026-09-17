<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_qr_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->decimal('amount', 12, 2);
            // The validated POS cart payload, replayed to actually create the
            // sale only once an admin confirms — nothing is deducted from
            // stock or billed until then.
            $table->longText('cart_snapshot');
            $table->enum('status', ['pending', 'confirmed', 'expired'])->default('pending');
            $table->string('bank_txn_id', 100)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id', 'fk_bqp_invoice')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('created_by_user_id', 'fk_bqp_created_by')->references('id')->on('users');
            $table->foreign('confirmed_by_user_id', 'fk_bqp_confirmed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_qr_payments');
    }
};
