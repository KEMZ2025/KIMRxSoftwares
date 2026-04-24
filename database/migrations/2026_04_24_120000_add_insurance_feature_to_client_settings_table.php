<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('client_settings', 'insurance_enabled')) {
            Schema::table('client_settings', function (Blueprint $table) {
                $table->boolean('insurance_enabled')->default(false)->after('reports_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('client_settings', 'insurance_enabled')) {
            Schema::table('client_settings', function (Blueprint $table) {
                $table->dropColumn('insurance_enabled');
            });
        }
    }
};
