<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 30)->unique();
            $table->string('qr_payload', 255)->nullable();
            $table->string('item_type', 60);
            $table->unsignedTinyInteger('metal_type_id');
            $table->unsignedInteger('purity_id');
            $table->decimal('gross_weight_grams', 10, 3);
            $table->decimal('stone_weight_grams', 10, 3)->default(0);
            $table->decimal('cutting_loss_grams', 10, 3)->default(0);
            $table->decimal('net_weight_grams', 10, 3)
                ->storedAs('gross_weight_grams - stone_weight_grams - cutting_loss_grams');
            $table->decimal('labour_cost', 12, 2)->default(0);
            $table->decimal('polish_cost', 12, 2)->default(0);
            $table->decimal('purchase_rate_per_gram', 12, 2);
            $table->decimal('purchase_price', 12, 2);
            $table->unsignedBigInteger('source_party_id')->nullable();
            $table->date('date_received');
            $table->enum('status', ['in_stock', 'sold', 'bought_back'])->default('in_stock');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('metal_type_id', 'fk_item_metal')->references('id')->on('metal_types');
            $table->foreign('purity_id', 'fk_item_purity')->references('id')->on('purities');
            $table->foreign('source_party_id', 'fk_item_source_party')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('created_by_user_id', 'fk_item_created_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'idx_item_status');
            $table->index(['metal_type_id', 'purity_id'], 'idx_item_metal_purity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
