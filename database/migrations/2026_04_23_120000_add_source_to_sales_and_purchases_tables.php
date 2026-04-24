<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('source')
                ->default('live')
                ->after('receipt_number');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->string('source')
                ->default('live')
                ->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
