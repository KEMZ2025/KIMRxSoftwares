<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->after('receipt_number');
            $table->unique(
                ['client_id', 'branch_id', 'submission_token'],
                'sales_submission_token_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('sales_submission_token_unique');
            $table->dropColumn('submission_token');
        });
    }
};
