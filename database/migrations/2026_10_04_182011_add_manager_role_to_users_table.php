<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Adds 'manager' to role so an admin can grant a trusted non-admin user specific, admin-gated abilities (e.g. confirming Bank QR payments) without making them a full admin. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'shopkeeper'])->default('shopkeeper')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'shopkeeper'])->default('shopkeeper')->change();
        });
    }
};
