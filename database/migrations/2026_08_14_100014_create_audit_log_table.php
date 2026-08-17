<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->enum('entity_type', [
                'item_price', 'sale_line_price', 'rate', 'purity',
                'buyback_deduction', 'transaction_weight', 'discount',
            ]);
            $table->unsignedBigInteger('entity_id');
            $table->string('field_name', 60);
            $table->string('old_value', 255)->nullable();
            $table->string('new_value', 255);
            $table->string('reason', 255);
            $table->unsignedBigInteger('changed_by_user_id');
            $table->timestamp('changed_at')->useCurrent();

            $table->foreign('changed_by_user_id', 'fk_audit_user')->references('id')->on('users');
            $table->index(['entity_type', 'entity_id'], 'idx_audit_entity');
            $table->index('changed_at', 'idx_audit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
