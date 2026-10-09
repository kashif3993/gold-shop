<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Where the uploaded bank slip (if any) was saved on the 'bank-slips' disk — see BankQrPaymentController::scanSlip(). */
    public function up(): void
    {
        Schema::table('bank_qr_payments', function (Blueprint $table) {
            $table->string('slip_path')->nullable()->after('bank_txn_id');
        });
    }

    public function down(): void
    {
        Schema::table('bank_qr_payments', function (Blueprint $table) {
            $table->dropColumn('slip_path');
        });
    }
};
