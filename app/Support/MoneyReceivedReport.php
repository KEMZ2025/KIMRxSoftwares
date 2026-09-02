<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MoneyReceivedReport
{
    private const NET_COLLECTIONS = 'COALESCE(SUM(CASE WHEN reversal_of_payment_id IS NULL THEN amount ELSE -amount END), 0)';

    public static function checkoutSales(Builder $salesQuery): Builder
    {
        // amount_paid is cumulative. Remove collections across ALL dates to recover checkout receipts.
        $collections = Payment::query()
            ->whereColumn('payments.sale_id', 'sales.id')
            ->whereColumn('payments.client_id', 'sales.client_id')
            ->whereColumn('payments.branch_id', 'sales.branch_id')
            ->where('status', '!=', 'pending')
            ->selectRaw(self::NET_COLLECTIONS);

        $sales = (clone $salesQuery)
            ->select('sales.*')
            ->selectSub($collections, 'net_customer_collections');

        // Insurance amount_paid also includes insurer remittances; only the original copay belongs at checkout.
        $checkoutAmount = "CASE WHEN sales.payment_type = 'insurance' THEN sales.upfront_amount_paid
            ELSE sales.amount_paid - sales.net_customer_collections END";

        return Sale::query()
            ->fromSub($sales, 'sales')
            ->select('sales.*')
            ->selectRaw($checkoutAmount . ' AS checkout_amount')
            ->whereRaw('(' . $checkoutAmount . ') > 0');
    }

    public static function summarize(Builder $salesQuery, Builder $paymentsQuery): array
    {
        $definitions = PaymentMethodBuckets::definitions();
        $totals = array_fill_keys(array_column($definitions, 'key'), 0.0);
        $checkoutTotal = 0.0;

        $checkoutByMethod = DB::query()
            ->fromSub(self::checkoutSales($salesQuery), 'checkout_sales')
            ->select('payment_method')
            ->selectRaw('SUM(checkout_amount) AS total_amount')
            ->groupBy('payment_method')
            ->get();

        foreach ($checkoutByMethod as $receipt) {
            $amount = (float) $receipt->total_amount;
            $checkoutTotal += $amount;
            // Legacy mixed-method invoices no longer retain their original checkout method.
            $key = strtolower(trim((string) $receipt->payment_method)) === 'mixed'
                ? 'unallocated'
                : PaymentMethodBuckets::normalize($receipt->payment_method);
            $totals[$key] = ($totals[$key] ?? 0.0) + $amount;
        }

        $collectionsByMethod = (clone $paymentsQuery)
            ->select('payment_method')
            ->selectRaw(self::NET_COLLECTIONS . ' AS total_amount')
            ->groupBy('payment_method')
            ->get();

        $collectionsTotal = 0.0;
        foreach ($collectionsByMethod as $collection) {
            $amount = (float) $collection->total_amount;
            $collectionsTotal += $amount;
            $key = PaymentMethodBuckets::normalize($collection->payment_method);
            $totals[$key] += $amount;
        }

        if (isset($totals['unallocated'])) {
            $definitions[] = ['key' => 'unallocated', 'label' => 'Unallocated', 'tone' => 'cheque'];
        }

        return [
            'checkout' => round($checkoutTotal, 2),
            'collections' => round($collectionsTotal, 2),
            'total' => round($checkoutTotal + $collectionsTotal, 2),
            'byMethod' => array_map(fn (array $definition) => [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'amount' => round($totals[$definition['key']], 2),
                'tone' => $definition['tone'],
            ], $definitions),
        ];
    }
}
