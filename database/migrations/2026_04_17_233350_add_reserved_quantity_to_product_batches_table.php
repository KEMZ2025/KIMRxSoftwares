<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 14, 2)->default(0)->after('quantity_available');
        });
    }

    public function down(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropColumn('reserved_quantity');
        });
    }
};