<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->string('source')->default('manual')->after('status');
        });

        DB::table('supplier_payments')->update([
            'source' => 'invoice_entry',
        ]);
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
