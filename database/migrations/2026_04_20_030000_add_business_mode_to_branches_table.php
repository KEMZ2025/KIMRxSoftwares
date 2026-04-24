<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->enum('business_mode', ['inherit', 'retail_only', 'wholesale_only', 'both'])
                ->default('inherit')
                ->after('address');
        });

        DB::table('branches')
            ->whereNull('business_mode')
            ->update(['business_mode' => 'inherit']);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('business_mode');
        });
    }
};
