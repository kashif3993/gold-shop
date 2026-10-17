<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds 'invoice_payment' to entity_type so recording/changing a partial payment is audited like any other protected money write. */
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->enum('entity_type', [
                'item_price', 'sale_line_price', 'rate', 'purity',
                'buyback_deduction', 'transaction_weight', 'discount', 'settings', 'invoice_payment',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->enum('entity_type', [
                'item_price', 'sale_line_price', 'rate', 'purity',
                'buyback_deduction', 'transaction_weight', 'discount', 'settings',
            ])->change();
        });
    }
};
