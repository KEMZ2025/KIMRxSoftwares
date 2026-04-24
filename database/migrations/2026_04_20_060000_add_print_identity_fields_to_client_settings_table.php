<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->string('currency_symbol', 20)->default('UGX')->after('hide_discount_line_on_print');
            $table->string('tax_label', 50)->default('TIN')->after('currency_symbol');
            $table->string('tax_number')->nullable()->after('tax_label');
            $table->string('receipt_header')->nullable()->after('tax_number');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
            $table->text('invoice_footer')->nullable()->after('receipt_footer');
            $table->text('report_footer')->nullable()->after('invoice_footer');
            $table->boolean('show_logo_on_print')->default(true)->after('report_footer');
            $table->boolean('show_branch_contacts_on_print')->default(true)->after('show_logo_on_print');
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'currency_symbol',
                'tax_label',
                'tax_number',
                'receipt_header',
                'receipt_footer',
                'invoice_footer',
                'report_footer',
                'show_logo_on_print',
                'show_branch_contacts_on_print',
            ]);
        });
    }
};
