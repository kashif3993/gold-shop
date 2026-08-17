<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('metal_type_id');
            $table->unsignedInteger('purity_id');
            $table->decimal('api_raw_rate_per_gram', 12, 2)->nullable();
            $table->enum('adjustment_type_used', ['amount', 'percent'])->nullable();
            $table->decimal('adjustment_value_used', 10, 4)->nullable();
            $table->decimal('rate_per_gram', 12, 2);
            $table->date('rate_date');
            $table->enum('source', ['api', 'manual'])->default('api');
            $table->dateTime('fetched_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->boolean('is_stale')->default(false);
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('metal_type_id', 'fk_rate_metal')->references('id')->on('metal_types');
            $table->foreign('purity_id', 'fk_rate_purity')->references('id')->on('purities');
            $table->foreign('entered_by_user_id', 'fk_rate_user')->references('id')->on('users');
            $table->index(['metal_type_id', 'purity_id', 'is_current'], 'idx_rate_lookup');
            $table->index(['metal_type_id', 'purity_id', 'fetched_at'], 'idx_rate_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rates');
    }
};
