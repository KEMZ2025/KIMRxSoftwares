<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'expiry_alert_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('expiry_alert_days')->nullable()->after('track_expiry');
            });
        }

        DB::table('products')
            ->where('track_expiry', true)
            ->whereNull('expiry_alert_days')
            ->update([
                'expiry_alert_days' => 90,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'expiry_alert_days')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('expiry_alert_days');
            });
        }
    }
};
