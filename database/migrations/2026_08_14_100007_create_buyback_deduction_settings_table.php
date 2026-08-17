<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyback_deduction_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedTinyInteger('metal_type_id')->nullable();
            $table->unsignedInteger('purity_id')->nullable();
            $table->decimal('deduction_percent', 5, 2);
            $table->boolean('is_shop_default')->default(false);
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('metal_type_id', 'fk_bbd_metal')->references('id')->on('metal_types');
            $table->foreign('purity_id', 'fk_bbd_purity')->references('id')->on('purities');
            $table->foreign('updated_by_user_id', 'fk_bbd_user')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyback_deduction_settings');
    }
};
