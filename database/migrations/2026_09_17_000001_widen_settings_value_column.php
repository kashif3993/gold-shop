<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settings can now hold an uploaded image (the shop's bank QR, as a base64
 * data URI) — MySQL's default TEXT column tops out around 64KB, too tight
 * for a photographed QR code. SQLite has no such limit on TEXT, so this is
 * a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE settings MODIFY value LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE settings MODIFY value TEXT NULL');
        }
    }
};
