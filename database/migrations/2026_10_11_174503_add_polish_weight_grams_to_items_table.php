<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The shop's polish margin is now taken as extra weight added back into
     * net weight, not a flat Rs charge — net_weight_grams (a stored
     * generated column) is rebuilt to include it. Existing rows default
     * polish_weight_grams to 0, so historical net weights are unchanged.
     * Uses the fluent schema builder (not raw SQL) so it works the same way
     * on MySQL in production and SQLite in tests, same as the original
     * table definition.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('polish_weight_grams', 10, 3)->default(0)->after('cutting_loss_grams');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('net_weight_grams', 10, 3)
                ->storedAs('gross_weight_grams - stone_weight_grams - cutting_loss_grams + polish_weight_grams')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('net_weight_grams', 10, 3)
                ->storedAs('gross_weight_grams - stone_weight_grams - cutting_loss_grams')
                ->change();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('polish_weight_grams');
        });
    }
};
