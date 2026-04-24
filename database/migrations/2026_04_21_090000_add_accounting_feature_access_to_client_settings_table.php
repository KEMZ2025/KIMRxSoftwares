<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->boolean('accounting_chart_enabled')->default(true)->after('support_enabled');
            $table->boolean('accounting_general_ledger_enabled')->default(true)->after('accounting_chart_enabled');
            $table->boolean('accounting_trial_balance_enabled')->default(true)->after('accounting_general_ledger_enabled');
            $table->boolean('accounting_journals_enabled')->default(true)->after('accounting_trial_balance_enabled');
            $table->boolean('accounting_vouchers_enabled')->default(true)->after('accounting_journals_enabled');
            $table->boolean('accounting_profit_loss_enabled')->default(true)->after('accounting_vouchers_enabled');
            $table->boolean('accounting_balance_sheet_enabled')->default(true)->after('accounting_profit_loss_enabled');
            $table->boolean('accounting_expenses_enabled')->default(true)->after('accounting_balance_sheet_enabled');
            $table->boolean('accounting_fixed_assets_enabled')->default(true)->after('accounting_expenses_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('client_settings', function (Blueprint $table) {
            $table->dropColumn([
                'accounting_chart_enabled',
                'accounting_general_ledger_enabled',
                'accounting_trial_balance_enabled',
                'accounting_journals_enabled',
                'accounting_vouchers_enabled',
                'accounting_profit_loss_enabled',
                'accounting_balance_sheet_enabled',
                'accounting_expenses_enabled',
                'accounting_fixed_assets_enabled',
            ]);
        });
    }
};
