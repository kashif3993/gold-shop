<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A dedicated table for the Metal Ledger module's rate book (Task 2).
     *
     * Deliberately NOT `daily_rates` — that table already backs the real,
     * live gold-shop app's Rate Management/POS/Item Entry screens. This
     * module is a separate hiring-brief exercise and must not alter shared
     * production schema. `metal_type_id`/`purity_id` reference the existing
     * lookup tables (read-only reference data, safe to share) but nothing
     * here is written to or read by the real app.
     */
    public function up(): void
    {
        Schema::create('metal_ledger_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('metal_type_id');
            $table->unsignedInteger('purity_id');
            $table->enum('rate_type', ['buy', 'sell']);
            $table->decimal('rate_per_gram', 12, 2);
            // The moment this rate takes effect — what every "which rate was
            // in force at time X" question is answered against.
            $table->dateTime('effective_at');
            $table->enum('source', ['fetch', 'manual']);
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            // Exactly one row is current per (metal, purity, rate_type) —
            // enforced in RateBookService, not just by convention.
            $table->boolean('is_current')->default(true);
            // A fetch that arrived after a same-day manual entry is stored,
            // not discarded — just never applied. Visible to the Admin
            // instead of silently overwriting their correction.
            $table->boolean('superseded_by_manual')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('metal_type_id')->references('id')->on('metal_types');
            $table->foreign('purity_id')->references('id')->on('purities');
            $table->foreign('entered_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['metal_type_id', 'purity_id', 'rate_type', 'is_current'], 'idx_mlr_current_lookup');
            $table->index(['metal_type_id', 'purity_id', 'rate_type', 'effective_at'], 'idx_mlr_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_ledger_rates');
    }
};
