<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The moment a line's rate was locked (see RateQuoteService). Nullable
     * and purely additive — every existing row, and every call path that
     * doesn't supply a quote, is unaffected; the quote-lock check is only
     * ever run when a caller actually provides one.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dateTime('quoted_at')->nullable()->after('rate_per_gram');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('quoted_at');
        });
    }
};
