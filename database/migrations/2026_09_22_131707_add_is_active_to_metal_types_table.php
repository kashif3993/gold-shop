<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lets a shop that deals in only one metal (e.g. silver-only) turn the other off from Settings. */
    public function up(): void
    {
        Schema::table('metal_types', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('metal_types', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
