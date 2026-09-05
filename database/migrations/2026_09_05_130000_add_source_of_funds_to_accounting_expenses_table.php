<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_expenses', function (Blueprint $table) {
            $table->string('source_of_funds', 100)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_expenses', function (Blueprint $table) {
            $table->dropColumn('source_of_funds');
        });
    }
};
