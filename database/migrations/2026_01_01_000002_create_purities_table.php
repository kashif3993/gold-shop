<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purity master data (Task 1, FR-1) — gold and silver each carry their
     * own, owner-managed list. Adding "Gold 20K" next year is a data entry
     * here, not a code change.
     */
    public function up(): void
    {
        Schema::create('purities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('metal_type_id');
            $table->string('name', 30);
            $table->decimal('fineness_percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('metal_type_id')->references('id')->on('metal_types');
            $table->unique(['metal_type_id', 'name'], 'uq_purity_per_metal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purities');
    }
};
