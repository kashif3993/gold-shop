<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zakat_inputs', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('cash_in_hand', 14, 2)->default(0);
            $table->decimal('liabilities_owed', 14, 2)->default(0);
            $table->decimal('nisab_gold_grams', 8, 3)->default(87.480);
            $table->decimal('nisab_silver_grams', 8, 3)->default(612.360);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('updated_by_user_id', 'fk_zakat_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zakat_inputs');
    }
};
