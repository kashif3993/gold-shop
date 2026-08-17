<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_fetch_log', function (Blueprint $table) {
            $table->id();
            $table->dateTime('attempted_at')->useCurrent();
            $table->boolean('success');
            $table->string('source_api', 60)->default('gold-api.com');
            $table->string('response_summary', 255)->nullable();
            $table->boolean('fallback_used')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_fetch_log');
    }
};
