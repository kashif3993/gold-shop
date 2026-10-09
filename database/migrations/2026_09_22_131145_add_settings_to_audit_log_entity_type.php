<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds 'settings' to entity_type so shop-wide toggles (tax, maintenance mode) can be audited like any other protected write. */
    public function up(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->enum('entity_type', [
                'item_price', 'sale_line_price', 'rate', 'purity',
                'buyback_deduction', 'transaction_weight', 'discount', 'settings',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->enum('entity_type', [
                'item_price', 'sale_line_price', 'rate', 'purity',
                'buyback_deduction', 'transaction_weight', 'discount',
            ])->change();
        });
    }
};
