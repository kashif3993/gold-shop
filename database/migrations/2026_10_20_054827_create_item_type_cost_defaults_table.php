<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PRD FR-24/FR-25: Admin can set a labour cost "either per item or per
     * item type" — today's Item Entry always has the per-item field; this
     * table backs the per-item-type default that prefills it. Keyed by the
     * same plain `item_type` string Item Entry already uses (ring, bangle,
     * biscuit, …) — there is no item-type lookup table to reference, by
     * design (see ItemController, which validates it as a plain string).
     *
     * No polish_cost_default here: the brief's own "Polish Cost" field was
     * since redesigned into polish_weight_grams (a weight, not a money
     * figure — see the migration adding that column), so there is no money
     * field left for a polish default to prefill.
     */
    public function up(): void
    {
        Schema::create('item_type_cost_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('item_type')->unique();
            $table->decimal('labour_cost_default', 10, 2)->default(0);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('updated_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_type_cost_defaults');
    }
};
