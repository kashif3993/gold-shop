<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dedicated table for the Metal Ledger module's movement ledger
     * (Task 3). Deliberately NOT `transactions` — that table already backs
     * the real, live gold-shop app's sales/buy-back flow. This module is a
     * separate hiring-brief exercise and must not alter shared production
     * schema. `party_id`/`metal_type_id`/`purity_id` reference the existing
     * lookup/master tables (read-only reference data, safe to share) but
     * nothing here is written to or read by the real app.
     *
     * One row shape for every gram that moves, whoever it moves to or from
     * — a customer sale, a buy-back, raw metal to a karigar, a wholesaler
     * purchase, buying off another shopkeeper, all the same shape.
     * Append-only: a mistake is corrected by a new row pointing back at the
     * one it corrects (`corrects_movement_id`), never by editing or
     * deleting the original.
     */
    public function up(): void
    {
        Schema::create('metal_ledger_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('party_id');
            $table->enum('direction', ['in', 'out']);
            $table->unsignedTinyInteger('metal_type_id');
            $table->unsignedInteger('purity_id');
            $table->decimal('weight_grams', 10, 3);
            // Computed at write time from weight_grams x the purity's
            // fineness — see PurityConversionService (Task 1). Stored, not
            // re-derived later, per the shop's "round once, at the point of
            // storage" rounding policy.
            $table->decimal('pure_weight_grams', 10, 3);
            $table->decimal('rate_per_gram', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->date('movement_date');
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->string('item_reference', 60)->nullable();
            $table->string('document_reference', 60)->nullable();
            $table->text('notes')->nullable();
            // Null = this is an original entry. Set = this row corrects the
            // movement at that id; the original is never touched.
            $table->foreignId('corrects_movement_id')->nullable()->constrained('metal_ledger_movements')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('party_id')->references('id')->on('parties');
            $table->foreign('metal_type_id')->references('id')->on('metal_types');
            $table->foreign('purity_id')->references('id')->on('purities');
            $table->foreign('entered_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['party_id', 'metal_type_id', 'movement_date'], 'idx_mlm_party_balance_lookup');
            $table->index(['movement_date'], 'idx_mlm_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_ledger_movements');
    }
};
