<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('supplier_payments')
            ->where('source', 'invoice_entry')
            ->whereRaw('LOWER(TRIM(payment_method)) = ?', ['cheque'])
            ->update([
                'payment_method' => 'petty_cash',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('supplier_payments')
            ->where('source', 'invoice_entry')
            ->where('payment_method', 'petty_cash')
            ->update([
                'payment_method' => 'cheque',
                'updated_at' => now(),
            ]);
    }
};
