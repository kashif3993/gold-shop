<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('party_type_id');
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('address', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('party_type_id')->references('id')->on('party_types');
            $table->index('name', 'idx_party_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
