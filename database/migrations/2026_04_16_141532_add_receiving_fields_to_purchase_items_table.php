<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('ordered_quantity', 14, 2)->default(0)->after('expiry_date');
            $table->decimal('received_quantity', 14, 2)->default(0)->after('ordered_quantity');
            $table->decimal('remaining_quantity', 14, 2)->default(0)->after('received_quantity');
            $table->string('line_status')->default('pending')->after('wholesale_price');
            // pending, partial_received, fully_received
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn([
                'ordered_quantity',
                'received_quantity',
                'remaining_quantity',
                'line_status',
            ]);
        });
    }
};