<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->boolean('cash_drawer_enabled')->default(false)->after('expiry_alerts_enabled');
            $table->decimal('cash_drawer_alert_threshold', 14, 2)->nullable()->after('cash_drawer_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'cash_drawer_enabled',
                'cash_drawer_alert_threshold',
            ]);
        });
    }
};
