<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dedicated table for Task 5's deduction cascade — deliberately NOT
     * the real app's `buyback_deduction_settings` table, same reasoning as
     * every other Metal Ledger table: this module must not share schema
     * with the live gold-shop product.
     *
     * One row with both `metal_type_id` and `purity_id` set is an exact
     * override; `purity_id` null applies to the whole metal; both null
     * (with `is_shop_default`) is the shop-wide fallback.
     */
    public function up(): void
    {
        Schema::create('metal_ledger_buyback_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('metal_type_id')->nullable();
            $table->unsignedInteger('purity_id')->nullable();
            $table->decimal('deduction_percent', 5, 2);
            $table->boolean('is_shop_default')->default(false);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('metal_type_id')->references('id')->on('metal_types')->nullOnDelete();
            $table->foreign('purity_id')->references('id')->on('purities')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['metal_type_id', 'purity_id'], 'idx_mlbd_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_ledger_buyback_deductions');
    }
};
