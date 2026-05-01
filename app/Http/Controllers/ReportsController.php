<?php

namespace App\Http\Controllers;

use App\Models\AccountingExpense;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Support\PaymentMethodBuckets;
use App\Support\Printing\CsvDownload;
use App\Support\Printing\DocumentBranding;
use App\Support\Printing\PdfDownload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private const LOSS_REASONS = [
        'count_loss',
        'damaged',
        'expired',
        'theft_loss',
        'sample_use',
        'other',
    ];

    public function index(Request $request)
    {
        return view('reports.index', $this->reportViewData($request));
    }

    public function print(Request $request)
    {
        $data = $this->reportViewData($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.reports.index', $data);
    }

    public function download(Request $request)
    {
        $data = $this->reportViewData($request);
        $section = $this->downloadSection($request);

        if ($this->downloadFormat($request) === 'pdf') {
            $data['branding'] = DocumentBranding::forUser($request->user());

            return PdfDownload::make(
                'reports-' . now()->format('Ymd-His') . '.pdf',
                'prints.reports.index',
                $data,
                'a4',
                'landscape'
            );
        }

        return CsvDownload::make(
            $this->downloadFileBase($section) . '-' . now()->format('Ymd-His') . '.csv',
            [],
            $this->buildDownloadRows($data, $section)
        );
    }

    private function downloadFormat(Request $request): string
    {
        return strtolower(trim($request->string('format')->toString())) === 'pdf'
            ? 'pdf'
            : 'csv';
    }

    private function downloadSection(Request $request): string
    {
        $section = strtolower(trim($request->string('section')->toString()));

        return in_array($section, ['sales', 'purchases', 'customers', 'stock_risk', 'performance', 'adjustments'], true)
            ? $section
            : 'full';
    }

    private function downloadFileBase(string $section): string
    {
        return $section === 'full'
            ? 'reports'
            : 'reports-' . str_replace('_', '-', $section);
    }

    private function buildDownloadRows(array $data, string $section): array
    {
        return match ($section) {
            'sales' => $this->salesDownloadRows($data),
            'purchases' => $this->purchaseDownloadRows($data),
            'customers' => $this->customerDownloadRows($data),
            'stock_risk' => $this->stockRiskDownloadRows($data),
            'performance' => $this->performanceDownloadRows($data),
            'adjustments' => $this->adjustmentsDownloadRows($data),
            default => $this->fullDownloadRows($data),
        };
    }

    private function fullDownloadRows(array $data): array
    {
        $rows = [];

        $rows[] = ['Report', 'Value'];
        foreach ($data['headlineCards'] as $card) {
            $rows[] = [$card['label'], (float) $card['value']];
        }

        $rows[] = [];
        $rows[] = ['Sales Channel', 'Revenue', 'COGS', 'Gross Profit', 'Discount', 'Invoices'];
        foreach ($data['salesChannelCards'] as $card) {
            $rows[] = [
                $card['label'],
                (float) $card['revenue'],
                (float) $card['cogs'],
                (float) $card['gross_profit'],
                (float) $card['discounts'],
                (int) $card['invoice_count'],
            ];
        }

        $rows[] = [
            $data['overallNetProfitCard']['label'],
            (float) $data['overallNetProfitCard']['value'],
            'Expenses',
            (float) $data['overallNetProfitCard']['expenses'],
            'Margin %',
            (float) $data['overallNetProfitCard']['margin'],
        ];

        $rows[] = [];
        $rows[] = ['Performance Metric', 'Value'];
        foreach ($data['inventoryRiskCards'] as $card) {
            $rows[] = [$card['label'], (float) $card['value']];
        }

        $rows[] = [];
        $rows[] = ['Profit & Loss Snapshot', 'Amount'];
        foreach ($data['profitLossRows'] as $row) {
            $rows[] = [$row['label'], (float) $row['amount']];
        }

        $rows[] = [];
        $rows[] = ['Money Received Method', 'Amount'];
        foreach ($data['moneyByMethod'] as $method) {
            $rows[] = [$method['label'], (float) $method['amount']];
        }

        $rows[] = [];
        $rows = array_merge($rows, $this->performanceDownloadRows($data));
        $rows[] = [];
        $rows = array_merge($rows, $this->salesDownloadRows($data));
        $rows[] = [];
        $rows = array_merge($rows, $this->purchaseDownloadRows($data));
        $rows[] = [];
        $rows = array_merge($rows, $this->customerDownloadRows($data));
        $rows[] = [];
        $rows = array_merge($rows, $this->stockRiskDownloadRows($data));
        $rows[] = [];
        $rows = array_merge($rows, $this->adjustmentsDownloadRows($data));

        $rows[] = [];
        $rows[] = ['Top Selling Product', 'Qty Sold', 'Revenue', 'Gross Margin'];
        foreach ($data['topSellingProducts'] as $row) {
            $rows[] = [
                $row->name,
                (float) $row->total_quantity,
                (float) $row->total_revenue,
                (float) $row->total_revenue - (float) $row->total_cost,
            ];
        }

        $rows[] = [];
        $rows[] = ['Receivable Invoice', 'Customer', 'Date', 'Total', 'Balance'];
        foreach ($data['receivables'] as $sale) {
            $rows[] = [
                $sale->invoice_number,
                $sale->customer?->name ?? 'Walk-in Customer',
                optional($sale->sale_date)->format('Y-m-d'),
                (float) $sale->total_amount,
                (float) $sale->balance_due,
            ];
        }

        $rows[] = [];
        $rows[] = ['Payable Invoice', 'Supplier', 'Date', 'Total', 'Balance', 'Entered By'];
        foreach ($data['payables'] as $purchase) {
            $rows[] = [
                $purchase->invoice_number,
                $purchase->supplier?->name ?? 'Unknown Supplier',
                optional($purchase->purchase_date)->format('Y-m-d'),
                (float) $purchase->total_amount,
                (float) $purchase->balance_due,
                $purchase->createdByUser?->name ?? 'System',
            ];
        }

        return $rows;
    }

    private function performanceDownloadRows(array $data): array
    {
        $rows = [['Staff Performance', 'Invoices', 'Units Sold', 'Revenue', 'Gross Profit']];

        foreach ($data['staffPerformance'] as $row) {
            $rows[] = [
                $row['staff_name'],
                (int) $row['invoice_count'],
                (float) $row['units_sold'],
                (float) $row['revenue'],
                (float) $row['gross_profit'],
            ];
        }

        return $rows;
    }

    private function salesDownloadRows(array $data): array
    {
        $rows = [['Sales Invoice', 'Receipt', 'Channel', 'Customer', 'Date', 'Served By', 'Payment Method', 'Total', 'Gross Profit', 'Paid', 'Balance']];

        foreach ($data['selectedSalesReport'] as $sale) {
            $rows[] = [
                $sale->invoice_number,
                $sale->receipt_number ?? 'N/A',
                $sale->sale_type_label ?? $this->saleTypeLabel($this->normalizeSaleType($sale->sale_type)),
                $sale->customer?->name ?? 'Walk-in Customer',
                optional($sale->sale_date)->format('Y-m-d'),
                $sale->servedByUser?->name ?? 'System',
                $sale->payment_method,
                (float) $sale->total_amount,
                (float) ($sale->gross_profit ?? 0),
                (float) $sale->amount_paid,
                (float) $sale->balance_due,
            ];
        }

        return $rows;
    }

    private function purchaseDownloadRows(array $data): array
    {
        $rows = [['Purchase Invoice', 'Supplier', 'Date', 'Entered By', 'Medicines Bought', 'Total', 'Paid', 'Balance', 'Status']];

        foreach ($data['selectedPurchaseReport'] as $purchase) {
            $rows[] = [
                $purchase->invoice_number,
                $purchase->supplier?->name ?? 'Unknown Supplier',
                optional($purchase->purchase_date)->format('Y-m-d'),
                $purchase->createdByUser?->name ?? 'System',
                (string) ($purchase->medicine_summary ?? $this->summarizePurchaseMedicines($purchase)),
                (float) $purchase->total_amount,
                (float) $purchase->amount_paid,
                (float) $purchase->balance_due,
                $purchase->payment_status,
            ];
        }

        return $rows;
    }

    private function summarizePurchaseMedicines(Purchase $purchase): string
    {
        $items = $purchase->relationLoaded('items')
            ? $purchase->items
            : $purchase->items()->with('product:id,name')->get();

        $summary = $items
            ->map(function ($item) {
                $productName = trim((string) optional($item->product)->name);
                $productName = $productName !== '' ? $productName : 'Unknown Medicine';

                $ordered = $this->formatReportQuantity($item->ordered_quantity);
                $received = $this->formatReportQuantity($item->received_quantity);
                $fallback = $this->formatReportQuantity($item->quantity);

                if ($ordered !== null && $received !== null && $ordered !== $received) {
                    return $productName . ' (received ' . $received . ' of ' . $ordered . ')';
                }

                $quantity = $received ?? $ordered ?? $fallback;

                return $quantity !== null
                    ? $productName . ' x ' . $quantity
                    : $productName;
            })
            ->filter()
            ->values();

        return $summary->isNotEmpty()
            ? $summary->implode('; ')
            : 'No medicine lines recorded';
    }

    private function formatReportQuantity($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $numericValue = (float) $value;

        if (abs($numericValue - round($numericValue)) < 0.00001) {
            return number_format($numericValue, 0, '.', '');
        }

        return rtrim(rtrim(number_format($numericValue, 2, '.', ''), '0'), '.');
    }

    private function customerDownloadRows(array $data): array
    {
        $rows = [['Channel', 'Customer', 'Invoices', 'Revenue', 'Gross Profit', 'Amount Paid', 'Balance Due', 'Collection Rate (%)']];

        foreach ($data['customerPerformance'] as $row) {
            $rows[] = [
                $row['sale_type_label'],
                $row['customer_name'],
                (int) $row['invoice_count'],
                (float) $row['revenue'],
                (float) $row['gross_profit'],
                (float) $row['amount_paid'],
                (float) $row['balance_due'],
                (float) $row['collection_rate'],
            ];
        }

        return $rows;
    }

    private function stockRiskDownloadRows(array $data): array
    {
        $rows = [['Out Of Stock Products', 'Batches', 'Available Stock', 'Reserved Stock', 'Free Stock']];

        foreach ($data['outOfStockProducts'] as $row) {
            $rows[] = [
                trim($row['product_name'] . ' ' . ($row['strength'] ?? '')),
                (int) $row['batch_count'],
                (float) $row['available_stock'],
                (float) $row['reserved_stock'],
                (float) $row['free_stock'],
            ];
        }

        $rows[] = [];
        $rows[] = ['Critical Medicine', 'Batch', 'Supplier', 'Expiry Date', 'Days To Expiry', 'Free Stock', 'Unit Cost', 'Likely Loss'];

        foreach ($data['criticalMedicines'] as $row) {
            $rows[] = [
                trim($row['product_name'] . ' ' . ($row['strength'] ?? '')),
                $row['batch_number'],
                $row['supplier_name'],
                optional($row['expiry_date'])->format('Y-m-d'),
                (int) $row['days_to_expiry'],
                (float) $row['free_stock'],
                (float) $row['purchase_price'],
                (float) $row['loss_value'],
            ];
        }

        return $rows;
    }

    private function adjustmentsDownloadRows(array $data): array
    {
        $rows = [['Date', 'Direction', 'Reason', 'Product', 'Batch', 'Adjusted By', 'Qty', 'Unit Cost', 'Inventory Impact', 'Loss Posted', 'Books Effect', 'Note']];

        foreach ($data['selectedAdjustmentReport'] as $adjustment) {
            $rows[] = [
                optional($adjustment->adjustment_date)->format('Y-m-d H:i'),
                $adjustment->direction_label,
                $adjustment->reason_label,
                trim(($adjustment->product?->name ?? 'Unknown Product') . ' ' . ($adjustment->product?->strength ?? '')),
                $adjustment->batch?->batch_number ?? 'N/A',
                $adjustment->adjustedByUser?->name ?? 'System',
                (float) $adjustment->quantity,
                (float) ($adjustment->unit_cost ?? 0),
                (float) ($adjustment->inventory_impact ?? 0),
                (float) ($adjustment->loss_amount ?? 0),
                (string) ($adjustment->books_effect ?? ''),
                (string) ($adjustment->note ?? ''),
            ];
        }

        return $rows;
    }

    private function reportViewData(Request $request): array
    {
        $user = Auth::user();
        $branch = $user->branch?->loadMissing('client');
        $client = $user->client;

        $clientId = (int) $user->client_id;
        $branchId = (int) $user->branch_id;

        [$period, $dateFrom, $dateTo] = $this->resolveDateRange($request);

        $businessMode = $branch?->effectiveBusinessMode();
        if (!in_array($businessMode, ['retail_only', 'wholesale_only', 'both'], true)) {
            $businessMode = in_array($client?->business_mode, ['retail_only', 'wholesale_only', 'both'], true)
                ? $client->business_mode
                : 'both';
        }

        $businessModeLabel = $branch?->effectiveBusinessModeLabel()
            ?? (Branch::businessModeLabels()[$businessMode] ?? 'Retail and Wholesale');
        $enabledSaleTypes = $this->enabledSaleTypesForBusinessMode($businessMode);

        $adjustmentDirectionOptions = $this->adjustmentDirectionOptions();
        $adjustmentReasonOptions = $this->adjustmentReasonOptions();

        $adjustmentDirection = strtolower(trim((string) $request->query('adjustment_direction', '')));
        if (!array_key_exists($adjustmentDirection, $adjustmentDirectionOptions)) {
            $adjustmentDirection = '';
        }

        $adjustmentReason = strtolower(trim((string) $request->query('adjustment_reason', '')));
        if (!array_key_exists($adjustmentReason, $adjustmentReasonOptions)) {
            $adjustmentReason = '';
        }

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

        $adjustmentsBase = StockAdjustment::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId);

        $selectedSales = (clone $operationalSalesBase)
            ->whereBetween('sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $selectedPurchases = (clone $operationalPurchasesBase)
            ->whereDate('purchase_date', '>=', $dateFrom->toDateString())
            ->whereDate('purchase_date', '<=', $dateTo->toDateString());

        $selectedPayments = (clone $paymentsBase)
            ->whereBetween('payment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $selectedSupplierPayments = (clone $supplierPaymentsBase)
            ->whereBetween('payment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $selectedAdjustments = (clone $adjustmentsBase)
            ->whereBetween('adjustment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);
        $filteredAdjustments = $this->applyAdjustmentFilters(clone $selectedAdjustments, $adjustmentDirection, $adjustmentReason);

        $netSales = (float) (clone $selectedSales)->sum('total_amount');
        $salesDiscounts = (float) (clone $selectedSales)->sum('discount_amount');
        $salesCount = (int) (clone $selectedSales)->count();
        $purchaseValue = (float) (clone $selectedPurchases)->sum('total_amount');
        $purchaseCount = (int) (clone $selectedPurchases)->count();
        $collectionsNet = $this->netCustomerCollections(clone $selectedPayments);
        $supplierPaymentsTotal = (float) (clone $selectedSupplierPayments)->sum('amount');
        $moneyByMethod = $this->buildMoneyByMethod(clone $selectedSales, clone $selectedPayments);
        $totalMoneyReceived = collect($moneyByMethod)->sum('amount');

        $costOfGoodsSold = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.purchase_price), 0) as total_cost')
            ->value('total_cost');

        $operatingExpensesTotal = (float) AccountingExpense::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereBetween('expense_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->sum('amount');

        $grossProfit = $netSales - $costOfGoodsSold;
        $lossBreakdown = $this->stockLossBreakdown($clientId, $branchId, $dateFrom, $dateTo);
        $adjustmentBreakdown = $this->adjustmentBreakdown($clientId, $branchId, $dateFrom, $dateTo, $adjustmentDirection, $adjustmentReason);
        $damagedGoodsLoss = (float) ($lossBreakdown['damaged'] ?? 0);
        $expiredGoodsLoss = (float) ($lossBreakdown['expired'] ?? 0);
        $otherStockLoss = collect($lossBreakdown)
            ->except(['damaged', 'expired'])
            ->sum();
        $totalStockLoss = collect($lossBreakdown)->sum();
        $netResultAfterStockLoss = $grossProfit - $totalStockLoss;
        $netProfitAfterExpenses = $netResultAfterStockLoss - $operatingExpensesTotal;
        $netProfitMargin = $netSales > 0 ? round(($netProfitAfterExpenses / $netSales) * 100, 1) : 0.0;
        $openReceivables = (float) (clone $salesBase)->sum('balance_due');
        $openPayables = (float) (clone $purchasesBase)->sum('balance_due');

        $saleTypeExpression = $this->saleTypeSqlExpression();

        $saleChannelRevenueRows = (clone $selectedSales)
            ->selectRaw($saleTypeExpression . ' as sale_channel')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as revenue')
            ->selectRaw('COALESCE(SUM(discount_amount), 0) as discounts')
            ->groupBy(DB::raw($saleTypeExpression))
            ->get()
            ->keyBy('sale_channel');

        $saleChannelCostRows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw($saleTypeExpression . ' as sale_channel')
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.purchase_price), 0) as cogs')
            ->groupBy(DB::raw($saleTypeExpression))
            ->get()
            ->keyBy('sale_channel');

        $salesChannelCards = collect($enabledSaleTypes)
            ->map(function (string $saleType) use ($saleChannelRevenueRows, $saleChannelCostRows) {
                $revenueRow = $saleChannelRevenueRows->get($saleType);
                $costRow = $saleChannelCostRows->get($saleType);
                $revenue = (float) ($revenueRow->revenue ?? 0);
                $cogs = (float) ($costRow->cogs ?? 0);
                $grossProfitForType = $revenue - $cogs;

                return [
                    'key' => $saleType,
                    'label' => $this->saleTypeLabel($saleType) . ' Sales',
                    'revenue' => round($revenue, 2),
                    'cogs' => round($cogs, 2),
                    'gross_profit' => round($grossProfitForType, 2),
                    'discounts' => round((float) ($revenueRow->discounts ?? 0), 2),
                    'invoice_count' => (int) ($revenueRow->invoice_count ?? 0),
                    'tone' => $saleType === 'wholesale' ? 'blue' : 'emerald',
                ];
            })
            ->values();

        $overallNetProfitCard = [
            'label' => 'Overall Net Profit',
            'value' => round($netProfitAfterExpenses, 2),
            'margin' => $netProfitMargin,
            'expenses' => round($operatingExpensesTotal, 2),
            'stock_loss' => round($totalStockLoss, 2),
            'tone' => $netProfitAfterExpenses >= 0 ? 'emerald' : 'rose',
        ];

        $cancelledSales = Sale::query()
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->operational()
            ->where('status', 'cancelled')
            ->whereBetween('cancelled_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]);

        $cancelledSalesCount = (int) (clone $cancelledSales)->count();
        $cancelledSalesValue = (float) (clone $cancelledSales)->sum('total_amount');

        $headlineCards = [
            ['label' => 'Net Sales', 'value' => $netSales, 'kind' => 'money', 'tone' => 'emerald'],
            ['label' => 'Gross Profit', 'value' => $grossProfit, 'kind' => 'money', 'tone' => $grossProfit >= 0 ? 'teal' : 'rose'],
            [
                'label' => 'Net Profit After Expenses',
                'value' => $netProfitAfterExpenses,
                'kind' => 'money',
                'meta' => ['label' => 'Operating expenses', 'value' => $operatingExpensesTotal, 'kind' => 'money'],
                'tone' => $netProfitAfterExpenses >= 0 ? 'emerald' : 'rose',
            ],
            ['label' => 'Stock Loss Posted', 'value' => $totalStockLoss, 'kind' => 'money', 'tone' => 'rose'],
            ['label' => 'Money Received', 'value' => $totalMoneyReceived, 'kind' => 'money', 'tone' => 'blue'],
            ['label' => 'Supplier Paid', 'value' => $supplierPaymentsTotal, 'kind' => 'money', 'tone' => 'amber'],
            ['label' => 'Open Receivables', 'value' => $openReceivables, 'kind' => 'money', 'tone' => 'violet'],
            ['label' => 'Open Payables', 'value' => $openPayables, 'kind' => 'money', 'tone' => 'slate'],
            [
                'label' => 'Cancelled Sales',
                'value' => $cancelledSalesCount,
                'kind' => 'count',
                'meta' => ['label' => 'Cancelled value', 'value' => $cancelledSalesValue, 'kind' => 'money'],
                'tone' => 'rose',
            ],
        ];

        $profitLossRows = [
            ['label' => 'Sales Revenue', 'amount' => $netSales, 'tone' => 'positive'],
            ['label' => 'Cost of Goods Sold', 'amount' => $costOfGoodsSold * -1, 'tone' => 'negative'],
            ['label' => 'Gross Profit', 'amount' => $grossProfit, 'tone' => $grossProfit >= 0 ? 'positive' : 'negative', 'strong' => true],
            ['label' => 'Damaged Goods Loss', 'amount' => $damagedGoodsLoss * -1, 'tone' => 'negative'],
            ['label' => 'Expired Goods Loss', 'amount' => $expiredGoodsLoss * -1, 'tone' => 'negative'],
            ['label' => 'Other Stock Losses', 'amount' => $otherStockLoss * -1, 'tone' => 'negative'],
            ['label' => 'Net Result After Stock Losses', 'amount' => $netResultAfterStockLoss, 'tone' => $netResultAfterStockLoss >= 0 ? 'positive' : 'negative', 'strong' => true],
            ['label' => 'Operating Expenses', 'amount' => $operatingExpensesTotal * -1, 'tone' => 'negative'],
            ['label' => 'Net Profit After Expenses', 'amount' => $netProfitAfterExpenses, 'tone' => $netProfitAfterExpenses >= 0 ? 'positive' : 'negative', 'strong' => true],
        ];

        $salesSummary = [
            ['label' => 'Approved Invoices', 'value' => $salesCount, 'kind' => 'count'],
            ['label' => 'Discounts Given', 'value' => $salesDiscounts, 'kind' => 'money'],
            ['label' => 'Customer Collections', 'value' => $collectionsNet, 'kind' => 'money'],
            ['label' => 'Cancelled Sales Value', 'value' => $cancelledSalesValue, 'kind' => 'money'],
        ];

        $purchaseSummary = [
            ['label' => 'Purchase Invoices', 'value' => $purchaseCount, 'kind' => 'count'],
            ['label' => 'Purchase Value', 'value' => $purchaseValue, 'kind' => 'money'],
            ['label' => 'Supplier Payments', 'value' => $supplierPaymentsTotal, 'kind' => 'money'],
            ['label' => 'Open Supplier Balance', 'value' => $openPayables, 'kind' => 'money'],
        ];

        $topSellingProducts = SaleItem::query()
            ->select(
                'sale_items.product_id',
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_amount) as total_revenue'),
                DB::raw('SUM(sale_items.quantity * sale_items.purchase_price) as total_cost')
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
            ->limit(8)
            ->get();

        $receivables = (clone $salesBase)
            ->with(['customer:id,name', 'servedByUser:id,name'])
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->limit(8)
            ->get();

        $payables = (clone $purchasesBase)
            ->with(['supplier:id,name', 'createdByUser:id,name'])
            ->where('balance_due', '>', 0)
            ->orderByDesc('balance_due')
            ->limit(8)
            ->get();

        $selectedSalesReport = (clone $selectedSales)
            ->with(['customer:id,name', 'servedByUser:id,name', 'items:id,sale_id,quantity,purchase_price,total_amount'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function (Sale $sale) {
                $normalizedSaleType = $this->normalizeSaleType($sale->sale_type);
                $grossProfitForSale = $sale->items->sum(function ($item) {
                    return (float) $item->total_amount - ((float) $item->quantity * (float) $item->purchase_price);
                });

                $sale->setAttribute('sale_type_label', $this->saleTypeLabel($normalizedSaleType));
                $sale->setAttribute('gross_profit', round((float) $grossProfitForSale, 2));

                return $sale;
            });

        $selectedPurchaseReport = (clone $selectedPurchases)
            ->with(['supplier:id,name', 'createdByUser:id,name', 'items.product:id,name'])
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(function ($purchase) {
                $purchase->setAttribute('medicine_summary', $this->summarizePurchaseMedicines($purchase));

                return $purchase;
            });

        $staffPerformance = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('users', 'users.id', '=', 'sales.served_by')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw("
                sales.served_by as user_id,
                COALESCE(users.name, 'Unassigned') as staff_name,
                COUNT(DISTINCT sales.id) as invoice_count,
                COALESCE(SUM(sale_items.quantity), 0) as units_sold,
                COALESCE(SUM(sale_items.total_amount), 0) as revenue,
                COALESCE(SUM(sale_items.total_amount - (sale_items.quantity * sale_items.purchase_price)), 0) as gross_profit
            ")
            ->groupBy('sales.served_by', 'users.name')
            ->orderByDesc('gross_profit')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'user_id' => $row->user_id,
                    'staff_name' => $row->staff_name,
                    'invoice_count' => (int) $row->invoice_count,
                    'units_sold' => round((float) $row->units_sold, 2),
                    'revenue' => round((float) $row->revenue, 2),
                    'gross_profit' => round((float) $row->gross_profit, 2),
                ];
            })
            ->values();

        $topPerformer = $staffPerformance->first();

        $customerPerformanceBase = Sale::query()
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw("
                " . $saleTypeExpression . " as sale_channel,
                sales.customer_id,
                COALESCE(customers.name, 'Walk-in Customer') as customer_name,
                COUNT(sales.id) as invoice_count,
                COALESCE(SUM(sales.total_amount), 0) as revenue,
                COALESCE(SUM(sales.amount_paid), 0) as amount_paid,
                COALESCE(SUM(sales.balance_due), 0) as balance_due
            ")
            ->groupBy(DB::raw($saleTypeExpression), 'sales.customer_id', 'customers.name')
            ->get()
            ->keyBy(function ($row) {
                return strtolower((string) $row->sale_channel) . '::' . ($row->customer_id ?? 'walk-in');
            });

        $customerProfitBase = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.client_id', $clientId)
            ->where('sales.branch_id', $branchId)
            ->where('sales.is_active', true)
            ->where('sales.status', 'approved')
            ->where(function ($query) {
                $query->whereNull('sales.source')
                    ->orWhere('sales.source', Sale::SOURCE_LIVE);
            })
            ->whereBetween('sales.sale_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->selectRaw("
                " . $saleTypeExpression . " as sale_channel,
                sales.customer_id,
                COALESCE(SUM(sale_items.total_amount - (sale_items.quantity * sale_items.purchase_price)), 0) as gross_profit
            ")
            ->groupBy(DB::raw($saleTypeExpression), 'sales.customer_id')
            ->get()
            ->keyBy(function ($row) {
                return strtolower((string) $row->sale_channel) . '::' . ($row->customer_id ?? 'walk-in');
            });

        $customerPerformance = $customerPerformanceBase
            ->map(function ($row, string $key) use ($customerProfitBase) {
                $revenue = (float) $row->revenue;
                $paid = (float) $row->amount_paid;
                $saleType = $this->normalizeSaleType($row->sale_channel);
                $profitRow = $customerProfitBase->get($key);

                return [
                    'sale_type' => $saleType,
                    'sale_type_label' => $this->saleTypeLabel($saleType),
                    'customer_id' => $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'invoice_count' => (int) $row->invoice_count,
                    'revenue' => round($revenue, 2),
                    'gross_profit' => round((float) ($profitRow->gross_profit ?? 0), 2),
                    'amount_paid' => round($paid, 2),
                    'balance_due' => round((float) $row->balance_due, 2),
                    'collection_rate' => $revenue > 0 ? round(($paid / $revenue) * 100, 1) : 0.0,
                ];
            })
            ->sort(function (array $left, array $right) {
                return ($right['gross_profit'] <=> $left['gross_profit'])
                    ?: ($right['revenue'] <=> $left['revenue'])
                    ?: strcasecmp($left['customer_name'], $right['customer_name']);
            })
            ->values();

        $customerPerformanceGroups = collect($enabledSaleTypes)
            ->map(function (string $saleType) use ($customerPerformance) {
                $rows = $customerPerformance
                    ->where('sale_type', $saleType)
                    ->take(8)
                    ->values();

                return [
                    'key' => $saleType,
                    'label' => $this->saleTypeLabel($saleType) . ' Customers',
                    'rows' => $rows,
                    'scale' => max((float) ($rows->max('revenue') ?? 0), 0.0),
                    'top_customer' => $rows->first(),
                ];
            })
            ->values();

        $currentStockBatches = ProductBatch::query()
            ->with(['product.unit', 'supplier'])
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->get();

        $outOfStockProducts = $currentStockBatches
            ->groupBy(fn (ProductBatch $batch) => (int) $batch->product_id)
            ->map(function ($batches) {
                $firstBatch = $batches->first();
                $product = $firstBatch?->product;
                $availableStock = (float) $batches->sum(fn (ProductBatch $batch) => (float) $batch->quantity_available);
                $reservedStock = (float) $batches->sum(fn (ProductBatch $batch) => (float) $batch->reserved_quantity);
                $freeStock = (float) $batches->sum(fn (ProductBatch $batch) => max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity));

                return [
                    'product_name' => $product?->name ?? 'Unknown Product',
                    'strength' => $product?->strength,
                    'unit_name' => $product?->unit?->name,
                    'batch_count' => $batches->count(),
                    'available_stock' => round($availableStock, 2),
                    'reserved_stock' => round($reservedStock, 2),
                    'free_stock' => round($freeStock, 2),
                ];
            })
            ->filter(fn (array $row) => $row['free_stock'] <= 0.0001)
            ->sortBy('product_name')
            ->values();

        $today = Carbon::today(config('app.timezone'));

        $criticalMedicines = $currentStockBatches
            ->map(function (ProductBatch $batch) use ($today) {
                $freeStock = max(0, (float) $batch->quantity_available - (float) $batch->reserved_quantity);
                $expiryDate = $batch->expiry_date;
                $alertDays = max(1, (int) ($batch->product?->expiry_alert_days ?? 90));
                $daysToExpiry = $expiryDate ? $today->diffInDays($expiryDate, false) : null;
                $lossValue = $freeStock * (float) $batch->purchase_price;

                return [
                    'product_name' => $batch->product?->name ?? 'Unknown Product',
                    'strength' => $batch->product?->strength,
                    'unit_name' => $batch->product?->unit?->name,
                    'batch_number' => $batch->batch_number,
                    'supplier_name' => $batch->supplier?->name ?? 'Unknown Supplier',
                    'expiry_date' => $expiryDate,
                    'alert_days' => $alertDays,
                    'days_to_expiry' => $daysToExpiry,
                    'free_stock' => round($freeStock, 2),
                    'purchase_price' => round((float) $batch->purchase_price, 2),
                    'loss_value' => round($lossValue, 2),
                    'risk_label' => $daysToExpiry === null
                        ? 'No expiry'
                        : ($daysToExpiry < 0 ? 'Expired' : ($daysToExpiry === 0 ? 'Expires today' : $daysToExpiry . ' days left')),
                ];
            })
            ->filter(function (array $row) {
                return $row['days_to_expiry'] !== null
                    && $row['free_stock'] > 0
                    && $row['days_to_expiry'] <= $row['alert_days'];
            })
            ->sortBy('days_to_expiry')
            ->values();

        $inventoryRiskCards = [
            [
                'label' => 'Top Performer Profit',
                'value' => (float) ($topPerformer['gross_profit'] ?? 0),
                'kind' => 'money',
                'subtitle' => $topPerformer['staff_name'] ?? 'No sales team data in this range',
                'tone' => 'teal',
            ],
            [
                'label' => 'Top Performer Revenue',
                'value' => (float) ($topPerformer['revenue'] ?? 0),
                'kind' => 'money',
                'subtitle' => $topPerformer ? ($topPerformer['invoice_count'] . ' invoices closed') : 'No sales recorded',
                'tone' => 'blue',
            ],
            [
                'label' => 'Out Of Stock Medicines',
                'value' => (float) $outOfStockProducts->count(),
                'kind' => 'count',
                'subtitle' => 'Products with no free stock across active batches',
                'tone' => 'rose',
            ],
            [
                'label' => 'Likely Loss Value',
                'value' => (float) $criticalMedicines->sum('loss_value'),
                'kind' => 'money',
                'subtitle' => $criticalMedicines->count() . ' expiry-risk batches still holding stock',
                'tone' => 'amber',
            ],
        ];

        $selectedAdjustmentReport = (clone $filteredAdjustments)
            ->with(['product:id,name,strength', 'batch:id,batch_number,purchase_price', 'adjustedByUser:id,name'])
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(function (StockAdjustment $adjustment) {
                $unitCost = round((float) ($adjustment->batch?->purchase_price ?? 0), 2);
                $stockValue = round((float) $adjustment->quantity * $unitCost, 2);
                $inventoryImpact = $adjustment->direction === 'increase'
                    ? $stockValue
                    : $stockValue * -1;
                $lossAmount = $this->adjustmentLossAmount($adjustment->direction, $adjustment->reason, $stockValue);

                $adjustment->setAttribute('direction_label', $this->adjustmentDirectionOptions()[$adjustment->direction] ?? ucfirst((string) $adjustment->direction));
                $adjustment->setAttribute('reason_label', $this->adjustmentReasonOptions()[$adjustment->reason] ?? ucwords(str_replace('_', ' ', (string) $adjustment->reason)));
                $adjustment->setAttribute('unit_cost', $unitCost);
                $adjustment->setAttribute('stock_value', $stockValue);
                $adjustment->setAttribute('inventory_impact', $inventoryImpact);
                $adjustment->setAttribute('loss_amount', $lossAmount);
                $adjustment->setAttribute('books_effect', $this->adjustmentBooksEffect($adjustment->direction, $adjustment->reason));

                return $adjustment;
            });

        $adjustmentIncreaseValue = (float) $adjustmentBreakdown
            ->where('direction_key', 'increase')
            ->sum('value');
        $adjustmentDecreaseValue = (float) $adjustmentBreakdown
            ->where('direction_key', 'decrease')
            ->sum('value');
        $adjustmentLossValue = (float) $adjustmentBreakdown
            ->sum('loss_value');
        $netInventoryMovement = $adjustmentIncreaseValue - $adjustmentDecreaseValue;
        $adjustmentCount = (int) (clone $filteredAdjustments)->count();

        $adjustmentSummaryCards = [
            ['label' => 'Inventory Increase Value', 'value' => round($adjustmentIncreaseValue, 2), 'kind' => 'money', 'tone' => 'emerald'],
            ['label' => 'Inventory Decrease Value', 'value' => round($adjustmentDecreaseValue, 2), 'kind' => 'money', 'tone' => 'rose'],
            ['label' => 'Loss Affecting Profit', 'value' => round($adjustmentLossValue, 2), 'kind' => 'money', 'tone' => 'amber'],
            [
                'label' => 'Net Inventory Movement',
                'value' => round($netInventoryMovement, 2),
                'kind' => 'money',
                'tone' => $netInventoryMovement >= 0 ? 'teal' : 'rose',
                'meta' => ['label' => 'Adjustments in filter', 'value' => $adjustmentCount, 'kind' => 'count'],
            ],
        ];

        $damagedGoods = StockAdjustment::query()
            ->with(['product:id,name', 'batch:id,batch_number,purchase_price', 'adjustedByUser:id,name'])
            ->where('client_id', $clientId)
            ->where('branch_id', $branchId)
            ->where('direction', 'decrease')
            ->where('reason', 'damaged')
            ->whereBetween('adjustment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->orderByDesc('adjustment_date')
            ->limit(10)
            ->get();

        return [
            'user' => $user,
            'clientName' => $client?->name ?? 'No Client',
            'branchName' => $branch?->name ?? 'No Branch',
            'businessMode' => $businessMode,
            'businessModeLabel' => $businessModeLabel,
            'filters' => [
                'period' => $period,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'adjustment_direction' => $adjustmentDirection,
                'adjustment_reason' => $adjustmentReason,
            ],
            'adjustmentDirectionOptions' => $adjustmentDirectionOptions,
            'adjustmentReasonOptions' => $adjustmentReasonOptions,
            'rangeLabel' => $this->rangeLabel($period, $dateFrom, $dateTo),
            'salesChannelCards' => $salesChannelCards,
            'overallNetProfitCard' => $overallNetProfitCard,
            'headlineCards' => $headlineCards,
            'profitLossRows' => $profitLossRows,
            'salesSummary' => $salesSummary,
            'purchaseSummary' => $purchaseSummary,
            'moneyByMethod' => $moneyByMethod,
            'adjustmentBreakdown' => $adjustmentBreakdown,
            'adjustmentSummaryCards' => $adjustmentSummaryCards,
            'inventoryRiskCards' => $inventoryRiskCards,
            'topPerformer' => $topPerformer,
            'staffPerformance' => $staffPerformance,
            'customerPerformance' => $customerPerformance,
            'customerPerformanceGroups' => $customerPerformanceGroups,
            'selectedSalesReport' => $selectedSalesReport,
            'selectedPurchaseReport' => $selectedPurchaseReport,
            'selectedAdjustmentReport' => $selectedAdjustmentReport,
            'outOfStockProducts' => $outOfStockProducts->take(10)->values(),
            'outOfStockProductCount' => $outOfStockProducts->count(),
            'criticalMedicines' => $criticalMedicines->take(10)->values(),
            'criticalMedicineCount' => $criticalMedicines->count(),
            'topSellingProducts' => $topSellingProducts,
            'receivables' => $receivables,
            'payables' => $payables,
            'damagedGoods' => $damagedGoods,
        ];
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

    private function netCustomerCollections($paymentsQuery): float
    {
        return (float) $paymentsQuery
            ->selectRaw('COALESCE(SUM(CASE WHEN reversal_of_payment_id IS NULL THEN amount ELSE amount * -1 END), 0) as net_total')
            ->value('net_total');
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
            $totals[PaymentMethodBuckets::normalize($method)] += (float) $amount;
        }

        $paymentMethodTotals = $paymentsQuery
            ->select(
                'payment_method',
                DB::raw('SUM(CASE WHEN reversal_of_payment_id IS NULL THEN amount ELSE amount * -1 END) as total_amount')
            )
            ->groupBy('payment_method')
            ->pluck('total_amount', 'payment_method');

        foreach ($paymentMethodTotals as $method => $amount) {
            $totals[PaymentMethodBuckets::normalize($method)] += (float) $amount;
        }

        return collect(PaymentMethodBuckets::definitions())
            ->map(function (array $definition) use ($totals) {
                return [
                    'label' => $definition['label'],
                    'amount' => round($totals[$definition['key']] ?? 0, 2),
                    'tone' => $definition['tone'],
                ];
            })
            ->all();
    }

    private function stockLossBreakdown(int $clientId, int $branchId, Carbon $dateFrom, Carbon $dateTo): array
    {
        return StockAdjustment::query()
            ->leftJoin('product_batches', 'product_batches.id', '=', 'stock_adjustments.product_batch_id')
            ->where('stock_adjustments.client_id', $clientId)
            ->where('stock_adjustments.branch_id', $branchId)
            ->where('stock_adjustments.direction', 'decrease')
            ->whereIn('stock_adjustments.reason', self::LOSS_REASONS)
            ->whereBetween('stock_adjustments.adjustment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->select(
                'stock_adjustments.reason',
                DB::raw('SUM(stock_adjustments.quantity * COALESCE(product_batches.purchase_price, 0)) as total_value')
            )
            ->groupBy('stock_adjustments.reason')
            ->pluck('total_value', 'stock_adjustments.reason')
            ->map(fn ($value) => round((float) $value, 2))
            ->all();
    }

    private function adjustmentBreakdown(int $clientId, int $branchId, Carbon $dateFrom, Carbon $dateTo, string $direction = '', string $reason = '')
    {
        return $this->applyAdjustmentFilters(
            StockAdjustment::query()
            ->leftJoin('product_batches', 'product_batches.id', '=', 'stock_adjustments.product_batch_id')
            ->where('stock_adjustments.client_id', $clientId)
            ->where('stock_adjustments.branch_id', $branchId)
            ->whereBetween('stock_adjustments.adjustment_date', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()]),
            $direction,
            $reason
        )
            ->select(
                'stock_adjustments.direction',
                'stock_adjustments.reason',
                DB::raw('SUM(stock_adjustments.quantity) as total_quantity'),
                DB::raw('SUM(stock_adjustments.quantity * COALESCE(product_batches.purchase_price, 0)) as total_value')
            )
            ->groupBy('stock_adjustments.direction', 'stock_adjustments.reason')
            ->orderBy('stock_adjustments.direction')
            ->orderBy('stock_adjustments.reason')
            ->get()
            ->map(function ($row) {
                $value = round((float) $row->total_value, 2);

                return [
                    'direction' => $this->adjustmentDirectionOptions()[$row->direction] ?? ucfirst((string) $row->direction),
                    'direction_key' => (string) $row->direction,
                    'reason' => $this->adjustmentReasonOptions()[$row->reason] ?? ucwords(str_replace('_', ' ', (string) $row->reason)),
                    'reason_key' => (string) $row->reason,
                    'quantity' => round((float) $row->total_quantity, 2),
                    'value' => $value,
                    'loss_value' => $this->adjustmentLossAmount((string) $row->direction, (string) $row->reason, $value),
                ];
            });
    }

    private function applyAdjustmentFilters($query, string $direction = '', string $reason = '')
    {
        if ($direction !== '') {
            $query->where('stock_adjustments.direction', $direction);
        }

        if ($reason !== '') {
            $query->where('stock_adjustments.reason', $reason);
        }

        return $query;
    }

    private function enabledSaleTypesForBusinessMode(string $businessMode): array
    {
        return match ($businessMode) {
            'retail_only' => ['retail'],
            'wholesale_only' => ['wholesale'],
            default => ['retail', 'wholesale'],
        };
    }

    private function saleTypeSqlExpression(): string
    {
        return "CASE WHEN LOWER(COALESCE(sales.sale_type, '')) = 'wholesale' THEN 'wholesale' ELSE 'retail' END";
    }

    private function normalizeSaleType(?string $saleType): string
    {
        return strtolower(trim((string) $saleType)) === 'wholesale'
            ? 'wholesale'
            : 'retail';
    }

    private function saleTypeLabel(string $saleType): string
    {
        return $saleType === 'wholesale'
            ? 'Wholesale'
            : 'Retail';
    }

    private function adjustmentDirectionOptions(): array
    {
        return [
            'increase' => 'Increase Stock',
            'decrease' => 'Decrease Stock',
        ];
    }

    private function adjustmentReasonOptions(): array
    {
        return [
            'count_gain' => 'Count Gain',
            'found_stock' => 'Found Stock',
            'supplier_return' => 'Supplier Return',
            'customer_return' => 'Customer Return',
            'count_loss' => 'Count Loss',
            'damaged' => 'Damaged',
            'expired' => 'Expired',
            'theft_loss' => 'Theft / Loss',
            'sample_use' => 'Sample / Internal Use',
            'other' => 'Other',
        ];
    }

    private function adjustmentLossAmount(string $direction, string $reason, float $value): float
    {
        return $direction === 'decrease' && in_array($reason, self::LOSS_REASONS, true)
            ? round($value, 2)
            : 0.0;
    }

    private function adjustmentBooksEffect(string $direction, string $reason): string
    {
        if ($direction === 'increase') {
            return match ($reason) {
                'customer_return' => 'Inventory value increases on the books after returned stock comes back.',
                'found_stock', 'count_gain' => 'Inventory asset increases on the books after a positive stock correction.',
                default => 'Inventory asset increases on the books for this stock addition.',
            };
        }

        return match ($reason) {
            'damaged', 'expired', 'count_loss', 'theft_loss', 'sample_use', 'other'
                => 'Inventory asset decreases on the books and the same value reduces profit as stock loss.',
            'supplier_return'
                => 'Inventory asset decreases on the books as stock is sent back to the supplier.',
            default
                => 'Inventory asset decreases on the books for this stock reduction.',
        };
    }
}
