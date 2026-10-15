<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task 3, FR-16 — new party types are an owner-level data entry, not a
     * ledger code change. Customer, Karigar, Wholesaler, Other Shop,
     * Company all go through the exact same ledger with no special-casing.
     */
    public function up(): void
    {
        Schema::create('party_types', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 30)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_types');
    }
};
