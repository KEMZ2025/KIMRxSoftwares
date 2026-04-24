@extends('prints.layout')

@php
    $pageTitle = 'Performance Reports';
    $pageBadge = 'Reports';
@endphp

@section('content')
    <div class="section">
        <div style="color:#667085;">Business Mode: {{ $businessModeLabel }} | Range: {{ $rangeLabel }}</div>
    </div>

    <div class="section">
        <h3>Retail And Wholesale Profit Summary</h3>
        <div class="summary-grid">
            @foreach($salesChannelCards as $card)
                <div class="summary-card">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value" style="font-size:22px;">{{ number_format((float) $card['revenue'], 2) }}</div>
                    <div style="color:#667085;">COGS {{ number_format((float) $card['cogs'], 2) }} | Profit {{ number_format((float) $card['gross_profit'], 2) }}</div>
                    <div style="color:#667085;">Discount {{ number_format((float) $card['discounts'], 2) }} | Invoices {{ number_format((float) $card['invoice_count'], 0) }}</div>
                </div>
            @endforeach
            <div class="summary-card">
                <div class="label">{{ $overallNetProfitCard['label'] }}</div>
                <div class="value" style="font-size:22px;">{{ number_format((float) $overallNetProfitCard['value'], 2) }}</div>
                <div style="color:#667085;">Expenses {{ number_format((float) $overallNetProfitCard['expenses'], 2) }} | Margin {{ number_format((float) $overallNetProfitCard['margin'], 1) }}%</div>
                <div style="color:#667085;">Stock loss {{ number_format((float) $overallNetProfitCard['stock_loss'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="summary-grid">
            @foreach($headlineCards as $card)
                <div class="summary-card">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value" style="font-size:22px;">
                        {{ $card['kind'] === 'money' ? number_format((float) $card['value'], 2) : number_format((float) $card['value'], 0) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h3>Performance Highlights</h3>
        <div class="summary-grid">
            @foreach($inventoryRiskCards as $card)
                <div class="summary-card">
                    <div class="label">{{ $card['label'] }}</div>
                    <div class="value" style="font-size:20px;">
                        {{ $card['kind'] === 'money' ? number_format((float) $card['value'], 2) : number_format((float) $card['value'], 0) }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h3>Profit &amp; Loss Snapshot</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Line</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($profitLossRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="amount">{{ number_format((float) $row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Money Received By Method</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Method</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($moneyByMethod as $method)
                        <tr>
                            <td>{{ $method['label'] }}</td>
                            <td class="amount">{{ number_format((float) $method['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Staff Performance</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th class="amount">Invoices</th>
                        <th class="amount">Units Sold</th>
                        <th class="amount">Revenue</th>
                        <th class="amount">Gross Profit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staffPerformance as $row)
                        <tr>
                            <td>{{ $row['staff_name'] }}</td>
                            <td class="amount">{{ number_format((float) $row['invoice_count'], 0) }}</td>
                            <td class="amount">{{ number_format((float) $row['units_sold'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['revenue'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['gross_profit'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No staff performance data in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Customer Performance</h3>
        @foreach($customerPerformanceGroups as $group)
            <div class="table-wrap" style="margin-bottom:14px;">
                <table>
                    <thead>
                        <tr>
                            <th colspan="7">{{ $group['label'] }}</th>
                        </tr>
                        <tr>
                            <th>Customer</th>
                            <th class="amount">Invoices</th>
                            <th class="amount">Revenue</th>
                            <th class="amount">Gross Profit</th>
                            <th class="amount">Paid</th>
                            <th class="amount">Balance</th>
                            <th class="amount">Collection Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($group['rows'] as $row)
                            <tr>
                                <td>{{ $row['customer_name'] }}</td>
                                <td class="amount">{{ number_format((float) $row['invoice_count'], 0) }}</td>
                                <td class="amount">{{ number_format((float) $row['revenue'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $row['gross_profit'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $row['amount_paid'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $row['balance_due'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $row['collection_rate'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No customer performance data in this channel.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="section">
        <h3>Top Selling Products</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="amount">Qty Sold</th>
                        <th class="amount">Revenue</th>
                        <th class="amount">Gross Margin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topSellingProducts as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td class="amount">{{ number_format((float) $row->total_quantity, 2) }}</td>
                            <td class="amount">{{ number_format((float) $row->total_revenue, 2) }}</td>
                            <td class="amount">{{ number_format((float) $row->total_revenue - (float) $row->total_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Sales Report</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Channel</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Served By</th>
                        <th>Method</th>
                        <th class="amount">Total</th>
                        <th class="amount">Gross Profit</th>
                        <th class="amount">Paid</th>
                        <th class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($selectedSalesReport as $sale)
                        <tr>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->sale_type_label ?? 'Retail' }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                            <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                            <td>{{ $sale->servedByUser?->name ?? 'System' }}</td>
                            <td>{{ $sale->payment_method }}</td>
                            <td class="amount">{{ number_format((float) $sale->total_amount, 2) }}</td>
                            <td class="amount">{{ number_format((float) ($sale->gross_profit ?? 0), 2) }}</td>
                            <td class="amount">{{ number_format((float) $sale->amount_paid, 2) }}</td>
                            <td class="amount">{{ number_format((float) $sale->balance_due, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10">No sales in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Purchase Report</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th>Entered By</th>
                        <th>Medicines Bought</th>
                        <th>Status</th>
                        <th class="amount">Total</th>
                        <th class="amount">Paid</th>
                        <th class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($selectedPurchaseReport as $purchase)
                        <tr>
                            <td>{{ $purchase->invoice_number }}</td>
                            <td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td>
                            <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                            <td>{{ $purchase->createdByUser?->name ?? 'System' }}</td>
                            <td>{{ $purchase->medicine_summary ?? 'No medicine lines recorded' }}</td>
                            <td>{{ ucfirst((string) $purchase->payment_status) }}</td>
                            <td class="amount">{{ number_format((float) $purchase->total_amount, 2) }}</td>
                            <td class="amount">{{ number_format((float) $purchase->amount_paid, 2) }}</td>
                            <td class="amount">{{ number_format((float) $purchase->balance_due, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No purchases in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Stock Adjustment Money Impact</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Direction</th>
                        <th>Reason</th>
                        <th>Medicine</th>
                        <th>Batch</th>
                        <th class="amount">Qty</th>
                        <th class="amount">Unit Cost</th>
                        <th class="amount">Inventory Impact</th>
                        <th class="amount">Loss Posted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($selectedAdjustmentReport as $adjustment)
                        <tr>
                            <td>{{ optional($adjustment->adjustment_date)->format('d M Y H:i') }}</td>
                            <td>{{ $adjustment->direction_label }}</td>
                            <td>{{ $adjustment->reason_label }}</td>
                            <td>{{ trim(($adjustment->product?->name ?? 'Unknown Product') . ' ' . ($adjustment->product?->strength ?? '')) }}</td>
                            <td>{{ $adjustment->batch?->batch_number ?? 'N/A' }}</td>
                            <td class="amount">{{ number_format((float) $adjustment->quantity, 2) }}</td>
                            <td class="amount">{{ number_format((float) ($adjustment->unit_cost ?? 0), 2) }}</td>
                            <td class="amount">{{ number_format((float) ($adjustment->inventory_impact ?? 0), 2) }}</td>
                            <td class="amount">{{ number_format((float) ($adjustment->loss_amount ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No stock adjustments matched the selected filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Stock Risk</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Out Of Stock Product</th>
                        <th class="amount">Batches</th>
                        <th class="amount">Available</th>
                        <th class="amount">Reserved</th>
                        <th class="amount">Free Stock</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($outOfStockProducts as $row)
                        <tr>
                            <td>{{ trim($row['product_name'] . ' ' . ($row['strength'] ?? '')) }}</td>
                            <td class="amount">{{ number_format((float) $row['batch_count'], 0) }}</td>
                            <td class="amount">{{ number_format((float) $row['available_stock'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['reserved_stock'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['free_stock'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No out-of-stock medicines right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-wrap" style="margin-top:14px;">
            <table>
                <thead>
                    <tr>
                        <th>Critical Medicine</th>
                        <th>Batch</th>
                        <th>Risk Window</th>
                        <th class="amount">Free Stock</th>
                        <th class="amount">Unit Cost</th>
                        <th class="amount">Likely Loss</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($criticalMedicines as $row)
                        <tr>
                            <td>{{ trim($row['product_name'] . ' ' . ($row['strength'] ?? '')) }}</td>
                            <td>{{ $row['batch_number'] }}</td>
                            <td>{{ $row['risk_label'] }}</td>
                            <td class="amount">{{ number_format((float) $row['free_stock'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['purchase_price'], 2) }}</td>
                            <td class="amount">{{ number_format((float) $row['loss_value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No expiry-risk medicines right now.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Outstanding Receivables</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="amount">Total</th>
                        <th class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receivables as $sale)
                        <tr>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in Customer' }}</td>
                            <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                            <td class="amount">{{ number_format((float) $sale->total_amount, 2) }}</td>
                            <td class="amount">{{ number_format((float) $sale->balance_due, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No receivables in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Outstanding Payables</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th class="amount">Total</th>
                        <th class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payables as $purchase)
                        <tr>
                            <td>{{ $purchase->invoice_number }}</td>
                            <td>{{ $purchase->supplier?->name ?? 'Unknown Supplier' }}</td>
                            <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                            <td class="amount">{{ number_format((float) $purchase->total_amount, 2) }}</td>
                            <td class="amount">{{ number_format((float) $purchase->balance_due, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No payables in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
