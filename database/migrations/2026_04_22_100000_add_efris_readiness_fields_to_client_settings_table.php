<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->boolean('efris_enabled')->default(false)->after('reports_enabled');
            $table->string('efris_environment', 20)->default('sandbox')->after('tax_number');
            $table->string('efris_tin')->nullable()->after('efris_environment');
            $table->string('efris_legal_name')->nullable()->after('efris_tin');
            $table->string('efris_business_name')->nullable()->after('efris_legal_name');
            $table->string('efris_branch_code', 100)->nullable()->after('efris_business_name');
            $table->string('efris_device_serial', 100)->nullable()->after('efris_branch_code');
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'efris_enabled',
                'efris_environment',
                'efris_tin',
                'efris_legal_name',
                'efris_business_name',
                'efris_branch_code',
                'efris_device_serial',
            ]);
        });
    }
};
