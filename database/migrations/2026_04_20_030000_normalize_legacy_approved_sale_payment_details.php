<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales')
            ->select([
                'id',
                'payment_type',
                'payment_method',
                'amount_paid',
                'amount_received',
            ])
            ->where('status', 'approved')
            ->where('is_active', true)
            ->where('amount_paid', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($sales) {
                foreach ($sales as $sale) {
                    $amountPaid = (float) $sale->amount_paid;
                    $amountReceived = max((float) $sale->amount_received, $amountPaid);
                    $paymentMethod = trim((string) ($sale->payment_method ?? ''));

                    if ($paymentMethod === '') {
                        $paymentMethod = $sale->payment_type === 'cash'
                            ? 'Cash'
                            : 'Legacy Unspecified';
                    }

                    DB::table('sales')
                        ->where('id', $sale->id)
                        ->update([
                            'payment_method' => $paymentMethod,
                            'amount_received' => $amountReceived,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
    }
};
