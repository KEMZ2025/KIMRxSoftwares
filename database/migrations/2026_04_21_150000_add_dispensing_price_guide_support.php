<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->boolean('dispensing_price_guide_enabled')
                ->default(false)
                ->after('proforma_enabled');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('dispensing_price_guide')
                ->nullable()
                ->after('expiry_alert_days');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('dispensing_price_guide');
        });

        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn('dispensing_price_guide_enabled');
        });
    }
};
