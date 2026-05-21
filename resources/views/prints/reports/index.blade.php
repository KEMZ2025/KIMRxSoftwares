@extends('prints.layout')

@php
    $centeredPrintHeader = true;
    $activeReport = $activeReport ?? 'overview';
    $activeReportMeta = $activeReportMeta ?? ['label' => 'Reports', 'description' => 'Reports'];
    $pageTitle = $activeReportMeta['label'];
    $pageBadge = 'Reports';
@endphp

@section('content')
    <div class="section">
        <div style="color:#667085;">Business Mode: {{ $businessModeLabel }} | Range: {{ $rangeLabel }}</div>
    </div>

    @switch($activeReport)
        @case('profit_detail')
            <div class="section">
                <h3>Profit By Dispenser And Customer</h3>
                <p style="color:#667085; margin-top:0;">
                    Channel: {{ $profitSaleTypeOptions[$filters['profit_sale_type']] ?? 'Wholesale Sales' }}
                    | Dispenser: {{ optional($profitDispenserOptions->firstWhere('id', (int) $filters['profit_dispenser_id']))->name ?? 'All Dispensers' }}
                    | Customer: {{ optional($profitCustomerOptions->firstWhere('id', (int) $filters['profit_customer_id']))->name ?? 'All Customers' }}
                </p>
                <table>
                    <thead><tr><th>Date</th><th>Invoice</th><th>Type</th><th>Dispenser</th><th>Customer</th><th>Product</th><th>Batch</th><th>Qty</th><th>Cost</th><th>Selling</th><th>Sales</th><th>Profit</th><th>Margin</th></tr></thead>
                    <tbody>
                        @forelse($profitDetailRows as $row)
                            <tr><td>{{ $row['sale_date'] ? \Carbon\Carbon::parse($row['sale_date'])->format('d M Y') : 'N/A' }}</td><td>{{ $row['invoice_number'] }}</td><td>{{ $row['sale_type_label'] }}</td><td>{{ $row['dispenser_name'] }}</td><td>{{ $row['customer_name'] }}</td><td>{{ $row['product_name'] }}</td><td>{{ $row['batch_number'] }}</td><td>{{ number_format((float) $row['quantity'], 2) }}</td><td>{{ number_format((float) $row['purchase_price'], 2) }}</td><td>{{ number_format((float) $row['unit_price'], 2) }}</td><td>{{ number_format((float) $row['total_amount'], 2) }}</td><td>{{ number_format((float) $row['gross_profit'], 2) }}</td><td>{{ number_format((float) $row['margin'], 1) }}%</td></tr>
                        @empty
                            <tr><td colspan="13">No profit details were found for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="totals-box">
                    <div>Net Sales <strong>UGX {{ number_format((float) $profitDetailTotals['revenue'], 2) }}</strong></div>
                    <div>Cost Amount <strong>UGX {{ number_format((float) $profitDetailTotals['cost'], 2) }}</strong></div>
                    <div>Gross Profit <strong>UGX {{ number_format((float) $profitDetailTotals['gross_profit'], 2) }}</strong></div>
                    <div>Margin <strong>{{ number_format((float) $profitDetailTotals['margin'], 1) }}%</strong></div>
                </div>
            </div>
            @break

        @case('profit_loss')
            <div class="section"><h3>Profit &amp; Loss Snapshot</h3><table><thead><tr><th>Line</th><th class="amount">Amount</th></tr></thead><tbody>@foreach($profitLossRows as $row)<tr><td>{{ $row['label'] }}</td><td class="amount">{{ number_format((float) $row['amount'], 2) }}</td></tr>@endforeach</tbody></table></div>
            @break

        @case('money_methods')
            <div class="section"><h3>Money Received By Method</h3><table><thead><tr><th>Method</th><th class="amount">Amount</th></tr></thead><tbody>@foreach($moneyByMethod as $method)<tr><td>{{ $method['label'] }}</td><td class="amount">{{ number_format((float) $method['amount'], 2) }}</td></tr>@endforeach</tbody></table></div>
            @break

        @case('staff')
            <div class="section"><h3>Staff Performance</h3><table><thead><tr><th>Staff</th><th class="amount">Invoices</th><th class="amount">Units Sold</th><th class="amount">Revenue</th><th class="amount">Gross Profit</th></tr></thead><tbody>@forelse($staffPerformance as $row)<tr><td>{{ $row['staff_name'] }}</td><td class="amount">{{ number_format((float) $row['invoice_count'], 0) }}</td><td class="amount">{{ number_format((float) $row['units_sold'], 2) }}</td><td class="amount">{{ number_format((float) $row['revenue'], 2) }}</td><td class="amount">{{ number_format((float) $row['gross_profit'], 2) }}</td></tr>@empty<tr><td colspan="5">No staff performance data in this range.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('customers')
            <div class="section"><h3>Customer Performance</h3>@foreach($customerPerformanceGroups as $group)<h4>{{ $group['label'] }}</h4><table><thead><tr><th>Customer</th><th class="amount">Invoices</th><th class="amount">Revenue</th><th class="amount">Gross Profit</th><th class="amount">Paid</th><th class="amount">Balance</th><th class="amount">Collection Rate</th></tr></thead><tbody>@forelse($group['rows'] as $row)<tr><td>{{ $row['customer_name'] }}</td><td class="amount">{{ number_format((float) $row['invoice_count'], 0) }}</td><td class="amount">{{ number_format((float) $row['revenue'], 2) }}</td><td class="amount">{{ number_format((float) $row['gross_profit'], 2) }}</td><td class="amount">{{ number_format((float) $row['amount_paid'], 2) }}</td><td class="amount">{{ number_format((float) $row['balance_due'], 2) }}</td><td class="amount">{{ number_format((float) $row['collection_rate'], 1) }}%</td></tr>@empty<tr><td colspan="7">No customer performance data in this channel.</td></tr>@endforelse</tbody></table>@endforeach</div>
            @break

        @case('sales')
            <div class="section"><h3>Sales Detail</h3><table><thead><tr><th>Invoice</th><th>Channel</th><th>Customer</th><th>Date</th><th>Served By</th><th>Method</th><th class="amount">Total</th><th class="amount">Gross Profit</th><th class="amount">Paid</th><th class="amount">Balance</th></tr></thead><tbody>@forelse($selectedSalesReport as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->sale_type_label ?? 'Retail' }}</td><td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td><td>{{ optional($sale->sale_date)->format('d M Y') }}</td><td>{{ $sale->servedByUser?->name ?? 'System' }}</td><td>{{ $sale->payment_method }}</td><td class="amount">{{ number_format((float) $sale->total_amount, 2) }}</td><td class="amount">{{ number_format((float) ($sale->gross_profit ?? 0), 2) }}</td><td class="amount">{{ number_format((float) $sale->amount_paid, 2) }}</td><td class="amount">{{ number_format((float) $sale->balance_due, 2) }}</td></tr>@empty<tr><td colspan="10">No approved sales were recorded in this period.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('purchases')
            <div class="section"><h3>Purchase Detail</h3><table><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Entered By</th><th>Medicines Bought</th><th>Status</th><th class="amount">Total</th><th class="amount">Paid</th><th class="amount">Balance</th></tr></thead><tbody>@forelse($selectedPurchaseReport as $purchase)<tr><td>{{ $purchase->invoice_number }}</td><td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td><td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td><td>{{ $purchase->createdByUser?->name ?? 'System' }}</td><td>{{ $purchase->medicine_summary ?? 'No medicine lines recorded' }}</td><td>{{ ucfirst((string) $purchase->payment_status) }}</td><td class="amount">{{ number_format((float) $purchase->total_amount, 2) }}</td><td class="amount">{{ number_format((float) $purchase->amount_paid, 2) }}</td><td class="amount">{{ number_format((float) $purchase->balance_due, 2) }}</td></tr>@empty<tr><td colspan="9">No purchases were recorded in this period.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('migrated_purchases')
            <div class="section"><h3>Migrated Purchase History</h3><table><thead><tr><th>Old Invoice</th><th>Supplier</th><th>Date</th><th>Medicine</th><th>Batch</th><th>Expiry</th><th class="amount">Qty</th><th class="amount">Unit Cost</th><th class="amount">Line Total</th></tr></thead><tbody>@forelse($migratedPurchaseReport as $purchase)@foreach($purchase->items as $item)<tr><td>{{ $purchase->invoice_number }}</td><td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td><td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td><td>{{ trim(($item->product?->name ?? 'Unknown Medicine') . ' ' . ($item->product?->strength ?? '')) }}</td><td>{{ $item->batch_number ?? 'N/A' }}</td><td>{{ $item->expiry_date ? \Carbon\Carbon::parse($item->expiry_date)->format('d M Y') : 'N/A' }}</td><td class="amount">{{ number_format((float) ($item->quantity ?? $item->received_quantity ?? 0), 2) }}</td><td class="amount">{{ number_format((float) $item->unit_cost, 2) }}</td><td class="amount">{{ number_format((float) $item->total_cost, 2) }}</td></tr>@endforeach@empty<tr><td colspan="9">No migrated purchase history was found in this period.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('adjustments')
            <div class="section"><h3>Stock Adjustments</h3><table><thead><tr><th>Date</th><th>Direction</th><th>Reason</th><th>Medicine</th><th>Batch</th><th class="amount">Qty</th><th class="amount">Unit Cost</th><th class="amount">Inventory Impact</th><th class="amount">Loss Posted</th><th>Books Effect</th></tr></thead><tbody>@forelse($selectedAdjustmentReport as $adjustment)<tr><td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td><td>{{ $adjustment->direction_label }}</td><td>{{ $adjustment->reason_label }}</td><td>{{ $adjustment->product?->name ?? 'Unknown Product' }}</td><td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td><td class="amount">{{ number_format((float) $adjustment->quantity, 2) }}</td><td class="amount">{{ number_format((float) ($adjustment->unit_cost ?? 0), 2) }}</td><td class="amount">{{ number_format((float) ($adjustment->inventory_impact ?? 0), 2) }}</td><td class="amount">{{ number_format((float) ($adjustment->loss_amount ?? 0), 2) }}</td><td>{{ $adjustment->books_effect ?? 'Inventory books updated.' }}</td></tr>@empty<tr><td colspan="10">No stock adjustments matched the current filter.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('stock_risk')
            <div class="section"><h3>Out Of Stock Medicines</h3><table><thead><tr><th>Medicine</th><th class="amount">Batches</th><th class="amount">Available</th><th class="amount">Reserved</th><th class="amount">Free Stock</th></tr></thead><tbody>@forelse($outOfStockProducts as $row)<tr><td>{{ $row['product_name'] }}</td><td class="amount">{{ number_format((float) $row['batch_count'], 0) }}</td><td class="amount">{{ number_format((float) $row['available_stock'], 2) }}</td><td class="amount">{{ number_format((float) $row['reserved_stock'], 2) }}</td><td class="amount">{{ number_format((float) $row['free_stock'], 2) }}</td></tr>@empty<tr><td colspan="5">No active products are completely out of free stock right now.</td></tr>@endforelse</tbody></table></div><div class="section"><h3>Likely Money To Lose</h3><table><thead><tr><th>Medicine</th><th>Batch</th><th>Risk Window</th><th class="amount">Free Stock</th><th class="amount">Unit Cost</th><th class="amount">Likely Loss</th></tr></thead><tbody>@forelse($criticalMedicines as $row)<tr><td>{{ $row['product_name'] }}</td><td>{{ $row['batch_number'] }}</td><td>{{ $row['risk_label'] }}</td><td class="amount">{{ number_format((float) $row['free_stock'], 2) }}</td><td class="amount">{{ number_format((float) $row['purchase_price'], 2) }}</td><td class="amount">{{ number_format((float) $row['loss_value'], 2) }}</td></tr>@empty<tr><td colspan="6">No active expiry-risk batches are currently holding free stock.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('damaged')
            <div class="section"><h3>Damaged Goods Report</h3><table><thead><tr><th>Date</th><th>Product</th><th>Batch</th><th class="amount">Qty</th><th class="amount">Unit Cost</th><th class="amount">Loss Value</th><th>Adjusted By</th></tr></thead><tbody>@forelse($damagedGoods as $adjustment)@php $unitCost = (float) ($adjustment->batch?->purchase_price ?? 0); $lossValue = (float) $adjustment->quantity * $unitCost; @endphp<tr><td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td><td>{{ $adjustment->product?->name ?? 'Unknown Product' }}</td><td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td><td class="amount">{{ number_format((float) $adjustment->quantity, 2) }}</td><td class="amount">{{ number_format($unitCost, 2) }}</td><td class="amount">{{ number_format($lossValue, 2) }}</td><td>{{ $adjustment->adjustedByUser?->name ?? 'System' }}</td></tr>@empty<tr><td colspan="7">No damaged-goods adjustments were recorded in this period.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('top_products')
            <div class="section"><h3>Top Selling Products</h3><table><thead><tr><th>Product</th><th class="amount">Qty Sold</th><th class="amount">Revenue</th><th class="amount">Gross Margin</th></tr></thead><tbody>@forelse($topSellingProducts as $row)<tr><td>{{ $row->name }}</td><td class="amount">{{ number_format((float) $row->total_quantity, 2) }}</td><td class="amount">{{ number_format((float) $row->total_revenue, 2) }}</td><td class="amount">{{ number_format((float) $row->total_revenue - (float) $row->total_cost, 2) }}</td></tr>@empty<tr><td colspan="4">No approved sale lines were recorded in this period.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('receivables')
            <div class="section"><h3>Current Outstanding Receivables</h3><table><thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th class="amount">Total</th><th class="amount">Paid</th><th class="amount">Balance</th></tr></thead><tbody>@forelse($receivables as $sale)<tr><td>{{ $sale->invoice_number }}</td><td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td><td>{{ optional($sale->sale_date)->format('d M Y') }}</td><td class="amount">{{ number_format((float) $sale->total_amount, 2) }}</td><td class="amount">{{ number_format((float) $sale->amount_paid, 2) }}</td><td class="amount">{{ number_format((float) $sale->balance_due, 2) }}</td></tr>@empty<tr><td colspan="6">No unpaid customer balances are outstanding right now.</td></tr>@endforelse</tbody></table></div>
            @break

        @case('payables')
            <div class="section"><h3>Current Outstanding Payables</h3><table><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Entered By</th><th class="amount">Total</th><th class="amount">Paid</th><th class="amount">Balance</th></tr></thead><tbody>@forelse($payables as $purchase)<tr><td>{{ $purchase->invoice_number }}</td><td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td><td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td><td>{{ $purchase->createdByUser?->name ?? 'System' }}</td><td class="amount">{{ number_format((float) $purchase->total_amount, 2) }}</td><td class="amount">{{ number_format((float) $purchase->amount_paid, 2) }}</td><td class="amount">{{ number_format((float) $purchase->balance_due, 2) }}</td></tr>@empty<tr><td colspan="7">No unpaid supplier balances are outstanding right now.</td></tr>@endforelse</tbody></table></div>
            @break

        @default
            <div class="section"><h3>Retail And Wholesale Profit Summary</h3><div class="summary-grid">@foreach($salesChannelCards as $card)<div class="summary-card"><div class="label">{{ $card['label'] }}</div><div class="value">{{ number_format((float) $card['revenue'], 2) }}</div><div style="color:#667085;">COGS {{ number_format((float) $card['cogs'], 2) }} | Profit {{ number_format((float) $card['gross_profit'], 2) }}</div></div>@endforeach<div class="summary-card"><div class="label">{{ $overallNetProfitCard['label'] }}</div><div class="value">{{ number_format((float) $overallNetProfitCard['value'], 2) }}</div><div style="color:#667085;">Expenses {{ number_format((float) $overallNetProfitCard['expenses'], 2) }} | Margin {{ number_format((float) $overallNetProfitCard['margin'], 1) }}%</div></div></div></div>
            <div class="section"><h3>Headline Metrics</h3><div class="summary-grid">@foreach($headlineCards as $card)<div class="summary-card"><div class="label">{{ $card['label'] }}</div><div class="value">{{ $card['kind'] === 'money' ? number_format((float) $card['value'], 2) : number_format((float) $card['value'], 0) }}</div></div>@endforeach</div></div>
    @endswitch
@endsection