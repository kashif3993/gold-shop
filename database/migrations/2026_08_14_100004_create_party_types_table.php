<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
