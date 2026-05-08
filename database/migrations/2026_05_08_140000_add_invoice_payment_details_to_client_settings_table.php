<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('client_settings', 'invoice_payment_details')) {
                $table->text('invoice_payment_details')->nullable()->after('invoice_footer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            if (Schema::hasColumn('client_settings', 'invoice_payment_details')) {
                $table->dropColumn('invoice_payment_details');
            }
        });
    }
};