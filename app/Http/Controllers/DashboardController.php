<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Support\InventoryExpiryAlerts;
use App\Support\PaymentMethodBuckets;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $clientId = (int) $user->client_id;
        $branchId = (int) $user->branch_id;

        [$period, $dateFrom, $dateTo] = $this->resolveDateRange($request);

        $clientName = $user->client?->name ?? 'No Client';
        $branchName = $user->branch?->name ?? 'No Branch';

        $salesBase = Sale::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('status', 'approved');
        $operationalSalesBase = (clone $salesBase)->operational();

        $purchasesBase = Purchase::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true);
        $operationalPurchasesBase = (clone $purchasesBase)->operational();

        $paymentsBase = Payment::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'pending');

        $supplierPaymentsBase = SupplierPayment::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId);

        $selectedSales = (clone $operationalSalesBase)
            ->whereBetween('sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $selectedPurchases = (clone $operationalPurchasesBase)
            ->whereBetween('purchase_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

        $selectedPayments = (clone $paymentsBase)
            ->whereBetween('payment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $selectedSupplierPayments = (clone $supplierPaymentsBase)
            ->whereBetween('payment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $moneyByMethod = $this->buildMoneyByMethod(
            clone $selectedSales,
            clone $selectedPayments
        );
        $totalReceived = collect($moneyByMethod)->sum('amount');

        $headlineStats = [
            [
                'label' => 'Total Products',
                'value' => Product::query()
                    ->where('client_id', $clientId)
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->count(),
                'note' => 'Active branch products',
                'tone' => 'teal',
            ],
            [
                'label' => 'Total Customers',
                'value' => Customer::query()
                    ->where('client_id', $clientId)
                    ->where('is_active', true)
                    ->count(),
                'note' => 'Client customer accounts',
                'tone' => 'blue',
            ],
            [
                'label' => 'Total Suppliers',
                'value' => Supplier::query()
                    ->where('client_id', $clientId)
                    ->where('is_active', true)
                    ->count(),
                'note' => 'Active supplier profiles',
                'tone' => 'violet',
            ],
            [
                'label' => 'Out of Stock',
                'value' => Product::query()
                    ->where('client_id', $clientId)
                    ->where('branch_id', $branchId)
                    ->where('is_active', true)
                    ->whereDoesntHave('batches', function ($query) use ($clientId, $branchId) {
                        $query->where('client_id', $clientId)
                            ->where('branch_id', $branchId)
                            ->where('is_active', true)
                            ->where('quantity_available', '>', 0);
                    })
                    ->count(),
                'note' => 'Products with no free stock',
                'tone' => 'amber',
            ],
            [
                'label' => 'Expiring Soon',
                'value' => $this->countExpiringSoonBatches($clientId, $branchId),
                'note' => 'Batches inside alert window',
                'tone' => 'rose',
            ],
            [
                'label' => 'Debtors',
                'value' => Customer::query()
                    ->where('client_id', $clientId)
                    ->where('is_active', true)
                    ->where('outstanding_balance', '>', 0)
                    ->count(),
                'note' => 'Customers still owing money',
                'tone' => 'slate',
            ],
        ];

        $salesValue = (float) (clone $selectedSales)->sum('total_amount');
        $purchaseValue = (float) (clone $selectedPurchases)->sum('total_amount');
        $supplierPaid = (float) (clone $selectedSupplierPayments)->sum('amount');
        $currentReceivables = (float) (clone $salesBase)->sum('balance_due');
        $currentPayables = (float) (clone $purchasesBase)->sum('balance_due');
        $creditCreated = (float) (clone $selectedSales)->sum('balance_due');
        $netBusiness = $salesValue - $purchaseValue;

        $financeStats = [
            [
                'label' => 'Sales Value',
                'value' => $salesValue,
                'note' => 'Approved sales in this window',
                'tone' => 'teal',
            ],
            [
                'label' => 'Purchases Value',
                'value' => $purchaseValue,
                'note' => 'Purchase invoices entered in this window',
                'tone' => 'blue',
            ],
            [
                'label' => 'Money Received',
                'value' => $totalReceived,
                'note' => 'POS receipts plus customer collections',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Supplier Paid',
                'value' => $supplierPaid,
                'note' => 'Supplier payments recorded in this window',
                'tone' => 'amber',
            ],
            [
                'label' => 'Credit Due',
                'value' => $creditCreated,
                'note' => 'Unpaid balance created in this window',
                'tone' => 'violet',
            ],
            [
                'label' => 'Open Receivables',
                'value' => $currentReceivables,
                'note' => 'Current customer balances still open',
                'tone' => 'rose',
            ],
            [
                'label' => 'Open Payables',
                'value' => $currentPayables,
                'note' => 'Current supplier balances still open',
                'tone' => 'slate',
            ],
            [
                'label' => 'Sales vs Purchases',
                'value' => $netBusiness,
                'note' => 'Selected sales value minus purchase value',
                'tone' => $netBusiness >= 0 ? 'teal' : 'rose',
            ],
        ];

        $trendChart = $this->buildSalesPurchaseTrend(
            clone $operationalSalesBase,
            clone $operationalPurchasesBase,
            $dateFrom,
            $dateTo
        );

        $topMovingProducts = SaleItem::query()
            ->select(
                'sale_items.product_id',
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_amount) as total_revenue')
            )
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->groupBy('sale_items.product_id', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_items.quantity)'))
            ->orderByDesc(DB::raw('SUM(sale_items.total_amount)'))
            ->limit(6)
            ->get();

        $recentPosReceipts = (clone $selectedSales)
            ->where('amount_paid', '>', 0)
            ->with(['customer:id,name', 'servedByUser:id,name'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'timestamp' => $sale->created_at?->timestamp ?? now()->timestamp,
                    'date' => $sale->created_at?->format('d M Y H:i') ?? $sale->sale_date?->format('d M Y'),
                    'source' => 'POS Sale',
                    'reference' => $sale->receipt_number ?: $sale->invoice_number,
                    'party' => $sale->customer?->name ?? 'Walk-in Customer',
                    'method' => $sale->payment_method ?: 'Cash',
                    'amount' => (float) $sale->amount_paid,
                    'actor' => $sale->servedByUser?->name ?? 'System',
                ];
            });

        $recentCollections = (clone $selectedPayments)
            ->whereNull('reversal_of_payment_id')
            ->with(['customer:id,name', 'receivedByUser:id,name', 'sale:id,invoice_number,receipt_number'])
            ->orderByDesc('payment_date')
            ->limit(6)
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'timestamp' => $payment->payment_date?->timestamp ?? now()->timestamp,
                    'date' => $payment->payment_date?->format('d M Y H:i') ?? 'N/A',
                    'source' => 'Collection',
                    'reference' => $payment->reference_number
                        ?: $payment->sale?->receipt_number
                        ?: $payment->sale?->invoice_number
                        ?: 'N/A',
                    'party' => $payment->customer?->name ?? 'Customer',
                    'method' => $payment->payment_method ?: 'N/A',
                    'amount' => (float) $payment->amount,
                    'actor' => $payment->receivedByUser?->name ?? 'System',
                ];
            });

        $recentMoneyIn = $recentPosReceipts
            ->concat($recentCollections)
            ->sortByDesc('timestamp')
            ->take(6)
            ->values();

        $filters = [
            'period' => $period,
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
        ];

        return view('dashboard.index', [
            'user' => $user,
            'clientName' => $clientName,
            'branchName' => $branchName,
            'filters' => $filters,
            'rangeLabel' => $this->rangeLabel($period, $dateFrom, $dateTo),
            'headlineStats' => $headlineStats,
            'financeStats' => $financeStats,
            'moneyByMethod' => $moneyByMethod,
            'totalReceived' => $totalReceived,
            'trendChart' => $trendChart,
            'topMovingProducts' => $topMovingProducts,
            'recentMoneyIn' => $recentMoneyIn,
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $period = (string) $request->query('period', 'today');
        $today = Carbon::today();

        return match ($period) {
            'this_week' => ['this_week', $today->copy()->startOfWeek(), $today->copy()->endOfWeek()],
            'this_month' => ['this_month', $today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'custom' => $this->resolveCustomDateRange($request, $today),
            default => ['today', $today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
    }

    private function resolveCustomDateRange(Request $request, Carbon $today): array
    {
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse((string) $request->query('date_from'))->startOfDay()
            : $today->copy()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse((string) $request->query('date_to'))->endOfDay()
            : $dateFrom->copy()->endOfDay();

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        return ['custom', $dateFrom, $dateTo];
    }

    private function rangeLabel(string $period, Carbon $dateFrom, Carbon $dateTo): string
    {
        return match ($period) {
            'today' => 'Today, ' . $dateTo->format('d M Y'),
            'this_week' => 'This Week, ' . $dateFrom->format('d M') . ' - ' . $dateTo->format('d M Y'),
            'this_month' => 'This Month, ' . $dateFrom->format('d M') . ' - ' . $dateTo->format('d M Y'),
            default => 'Custom Range, ' . $dateFrom->format('d M Y') . ' - ' . $dateTo->format('d M Y'),
        };
    }

    private function buildMoneyByMethod($salesQuery, $paymentsQuery): array
    {
        $totals = [
            'cash' => 0.0,
            'mtn' => 0.0,
            'airtel' => 0.0,
            'bank' => 0.0,
            'cheque' => 0.0,
        ];

        $saleMethodTotals = $salesQuery
            ->where('amount_paid', '>', 0)
            ->select('payment_method', DB::raw('SUM(amount_paid) as total_amount'))
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method');

        foreach ($saleMethodTotals as $method => $amount) {
            $normalized = PaymentMethodBuckets::normalize($method);
            $totals[$normalized] += (float) $amount;
        }

        $paymentMethodTotals = $paymentsQuery
            ->select(
                'payment_method',
                DB::raw(
                    'SUM(CASE WHEN reversal_of_payment_id IS NULL THEN amount ELSE amount * -1 END) as total_amount'
                )
            )
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method');

        foreach ($paymentMethodTotals as $method => $amount) {
            $normalized = PaymentMethodBuckets::normalize($method);
            $totals[$normalized] += (float) $amount;
        }

        return collect(PaymentMethodBuckets::definitions())
            ->map(function (array $definition) use ($totals) {
                return [
                    'key' => $definition['key'],
                    'label' => $definition['label'],
                    'amount' => round($totals[$definition['key']] ?? 0, 2),
                    'tone' => $definition['tone'],
                ];
            })
            ->all();
    }

    private function buildSalesPurchaseTrend($salesBase, $purchasesBase, Carbon $dateFrom, Carbon $dateTo): array
    {
        $daysInRange = $dateFrom->copy()->startOfDay()->diffInDays($dateTo->copy()->endOfDay());

        if ($daysInRange < 2) {
            $chartFrom = $dateTo->copy()->subDays(6)->startOfDay();
            $chartTo = $dateTo->copy()->endOfDay();
            $bucket = 'daily';
            $subtitle = 'Last 7 days ending ' . $dateTo->format('d M Y');
        } elseif ($daysInRange <= 45) {
            $chartFrom = $dateFrom->copy()->startOfDay();
            $chartTo = $dateTo->copy()->endOfDay();
            $bucket = 'daily';
            $subtitle = $chartFrom->format('d M Y') . ' - ' . $chartTo->format('d M Y');
        } else {
            $chartFrom = $dateFrom->copy()->startOfMonth();
            $chartTo = $dateTo->copy()->endOfMonth();
            $bucket = 'monthly';
            $subtitle = $chartFrom->format('M Y') . ' - ' . $chartTo->format('M Y');
        }

        $labels = [];
        $salesSeries = [];
        $purchaseSeries = [];

        if ($bucket === 'daily') {
            $salesMap = $salesBase
                ->whereBetween('sale_date', [$chartFrom, $chartTo])
                ->select(DB::raw('DATE(sale_date) as bucket'), DB::raw('SUM(total_amount) as total_amount'))
                ->groupBy(DB::raw('DATE(sale_date)'))
                ->pluck('total_amount', 'bucket');

            $purchaseMap = $purchasesBase
                ->whereBetween('purchase_date', [$chartFrom->toDateString(), $chartTo->toDateString()])
                ->select('purchase_date as bucket', DB::raw('SUM(total_amount) as total_amount'))
                ->groupBy('purchase_date')
                ->pluck('total_amount', 'bucket');

            foreach (CarbonPeriod::create($chartFrom->copy()->startOfDay(), '1 day', $chartTo->copy()->startOfDay()) as $day) {
                $key = $day->format('Y-m-d');
                $labels[] = $day->format('d M');
                $salesSeries[] = round((float) ($salesMap[$key] ?? 0), 2);
                $purchaseSeries[] = round((float) ($purchaseMap[$key] ?? 0), 2);
            }
        } else {
            $salesMap = $salesBase
                ->whereBetween('sale_date', [$chartFrom, $chartTo])
                ->select(DB::raw("DATE_FORMAT(sale_date, '%Y-%m') as bucket"), DB::raw('SUM(total_amount) as total_amount'))
                ->groupBy(DB::raw("DATE_FORMAT(sale_date, '%Y-%m')"))
                ->pluck('total_amount', 'bucket');

            $purchaseMap = $purchasesBase
                ->whereBetween('purchase_date', [$chartFrom->toDateString(), $chartTo->toDateString()])
                ->select(DB::raw("DATE_FORMAT(purchase_date, '%Y-%m') as bucket"), DB::raw('SUM(total_amount) as total_amount'))
                ->groupBy(DB::raw("DATE_FORMAT(purchase_date, '%Y-%m')"))
                ->pluck('total_amount', 'bucket');

            $cursor = $chartFrom->copy()->startOfMonth();

            while ($cursor->lte($chartTo)) {
                $key = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $salesSeries[] = round((float) ($salesMap[$key] ?? 0), 2);
                $purchaseSeries[] = round((float) ($purchaseMap[$key] ?? 0), 2);
                $cursor->addMonth();
            }
        }

        return [
            'bucket' => $bucket,
            'labels' => $labels,
            'sales' => $salesSeries,
            'purchases' => $purchaseSeries,
            'subtitle' => $subtitle,
        ];
    }

    private function countExpiringSoonBatches(int $clientId, int $branchId): int
    {
        return InventoryExpiryAlerts::countForBranch($clientId, $branchId);
    }
}
