<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('client_settings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('client_id')->constrained()->cascadeOnDelete();

        $table->enum('business_mode', ['retail_only', 'wholesale_only', 'both'])->default('both');

        $table->boolean('retail_pos_enabled')->default(true);
        $table->boolean('wholesale_pos_enabled')->default(true);
        $table->boolean('purchases_enabled')->default(true);
        $table->boolean('suppliers_enabled')->default(true);
        $table->boolean('customers_enabled')->default(true);
        $table->boolean('inventory_enabled')->default(true);
        $table->boolean('accounts_enabled')->default(true);
        $table->boolean('reports_enabled')->default(true);
        $table->boolean('employees_enabled')->default(true);
        $table->boolean('proforma_enabled')->default(false);
        $table->boolean('support_enabled')->default(true);

        $table->boolean('show_purchase_price_in_pos')->default(false);
        $table->boolean('show_last_purchase_price_in_pos')->default(false);
        $table->boolean('hide_out_of_stock_in_search')->default(true);

        $table->boolean('allow_small_receipt')->default(true);
        $table->boolean('allow_small_invoice')->default(true);
        $table->boolean('allow_large_receipt')->default(true);
        $table->boolean('allow_large_invoice')->default(true);

        $table->boolean('allow_small_proforma')->default(false);
        $table->boolean('allow_large_proforma')->default(false);

        $table->boolean('hide_discount_line_on_print')->default(true);

        $table->integer('default_line_count')->default(1);
        $table->boolean('allow_add_one_line')->default(true);
        $table->boolean('allow_add_five_lines')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_settings');
    }
};
