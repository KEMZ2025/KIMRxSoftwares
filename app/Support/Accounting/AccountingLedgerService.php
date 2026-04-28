<?php

namespace App\Support\Accounting;

use App\Models\AccountingExpense;
use App\Models\InsuranceClaimAdjustment;
use App\Models\InsurancePayment;
use App\Models\Payment;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\SupplierPayment;
use App\Models\FixedAsset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountingLedgerService
{
    public function resolveDateRange(?string $from, ?string $to): array
    {
        $timezone = config('app.timezone');
        $today = Carbon::today($timezone);

        $start = $from
            ? Carbon::parse($from, $timezone)->startOfDay()
            : $today->copy()->startOfMonth()->startOfDay();

        $end = $to
            ? Carbon::parse($to, $timezone)->endOfDay()
            : $today->copy()->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }

    public function journalEntries(User $user, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return collect()
            ->merge($this->saleEntries($user, $from, $to))
            ->merge($this->customerPaymentEntries($user, $from, $to))
            ->merge($this->insurancePaymentEntries($user, $from, $to))
            ->merge($this->insuranceClaimAdjustmentEntries($user, $from, $to))
            ->merge($this->purchaseEntries($user, $from, $to))
            ->merge($this->supplierPaymentEntries($user, $from, $to))
            ->merge($this->expenseEntries($user, $from, $to))
            ->merge($this->fixedAssetAcquisitionEntries($user, $from, $to))
            ->merge($this->fixedAssetDepreciationEntries($user, $from, $to))
            ->merge($this->stockAdjustmentEntries($user, $from, $to))
            ->sortBy('sort_stamp')
            ->values();
    }

    public function buildHomeSummary(User $user, Carbon $from, Carbon $to): array
    {
        $periodEntries = $this->journalEntries($user, $from, $to);
        $periodBalances = $this->accountBalances($periodEntries);
        $entriesToDate = $this->journalEntries($user, null, $to);
        $balances = $this->accountBalances($entriesToDate);

        $salesRevenue = $this->balanceTotal($periodBalances, ['41000', '42000']);
        $costOfSales = $this->balanceTotal($periodBalances, ['51000']);
        $grossMargin = $salesRevenue - $costOfSales;
        $adjustmentLosses = $this->balanceTotal($periodBalances, ['52000', '52010', '52020', '52030', '52040']);
        $operatingExpenses = $this->balanceTotal($periodBalances, ['50100', '50200', '50300', '50400', '50500', '50600', '50700', '50900']);
        $supplierPayments = $this->periodEntryTotal($periodEntries, 'supplier_payment', 'credit');
        $customerCollections = $this->periodEntryTotal($periodEntries, 'customer_collection', 'debit');
        $fixedAssetAdditions = $this->balanceTotal($periodBalances, ['17200', '17300', '17400', '17500', '17900']);
        $depreciationExpense = $this->balanceTotal($periodBalances, ['50800']);
        $netProfit = $grossMargin - $operatingExpenses - $depreciationExpense - $adjustmentLosses;

        return [
            'categoryCards' => collect(ChartOfAccounts::categoryDefinitions())
                ->map(function (array $definition, string $key) use ($balances) {
                    $accounts = collect(ChartOfAccounts::groupedAccounts()[$key] ?? []);
                    $categoryBalance = $accounts->sum(function (array $account) use ($balances) {
                        return ChartOfAccounts::statementAmount(
                            $account,
                            (float) ($balances[$account['code']] ?? 0.0)
                        );
                    });

                    return [
                        ...$definition,
                        'account_count' => $accounts->count(),
                        'balance' => round((float) $categoryBalance, 2),
                    ];
                })
                ->values(),
            'summaryCards' => [
                [
                    'label' => 'Open Receivables',
                    'value' => $balances['11000'] ?? 0.0,
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Open Insurance Claims',
                    'value' => $balances['11100'] ?? 0.0,
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Open Payables',
                    'value' => $balances['21000'] ?? 0.0,
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Inventory Value',
                    'value' => $balances['12100'] ?? 0.0,
                    'tone' => 'teal',
                ],
                [
                    'label' => 'Period Sales Revenue',
                    'value' => $salesRevenue,
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Cost of Goods Sold',
                    'value' => $costOfSales,
                    'tone' => 'slate',
                ],
                [
                    'label' => 'Gross Margin',
                    'value' => $grossMargin,
                    'tone' => $grossMargin >= 0 ? 'emerald' : 'rose',
                ],
                [
                    'label' => 'Adjustment Losses',
                    'value' => $adjustmentLosses,
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Operating Expenses',
                    'value' => $operatingExpenses,
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Depreciation Expense',
                    'value' => $depreciationExpense,
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Fixed Asset Additions',
                    'value' => $fixedAssetAdditions,
                    'tone' => 'teal',
                ],
                [
                    'label' => 'Supplier Disbursements',
                    'value' => $supplierPayments,
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Customer Collections',
                    'value' => $customerCollections,
                    'tone' => 'blue',
                ],
                [
                    'label' => 'Net Profit / Loss',
                    'value' => $netProfit,
                    'tone' => $netProfit >= 0 ? 'emerald' : 'rose',
                ],
            ],
            'recentEntries' => $periodEntries->sortByDesc('sort_stamp')->take(8)->values(),
        ];
    }

    public function chartData(User $user, Carbon $to): array
    {
        $balances = $this->accountBalances($this->journalEntries($user, null, $to));
        $groupedAccounts = collect(ChartOfAccounts::groupedAccounts())
            ->map(function (array $accounts, string $category) use ($balances) {
                return collect($accounts)
                    ->map(function (array $account) use ($balances) {
                        $balance = (float) ($balances[$account['code']] ?? 0.0);

                        return [
                            ...$account,
                            'balance' => $balance,
                            'balance_display' => ChartOfAccounts::formatBalance($balance, $account['normal_balance']),
                        ];
                    })
                    ->values();
            })
            ->toArray();

        return [
            'categories' => ChartOfAccounts::categoryDefinitions(),
            'groupedAccounts' => $groupedAccounts,
            'balances' => $balances,
        ];
    }

    public function chartOfAccounts(User $user, Carbon $to): array
    {
        return $this->chartData($user, $to->copy()->endOfDay());
    }

    public function generalLedger(User $user, Carbon $from, Carbon $to, ?string $accountCode = null): array
    {
        $entriesToDate = $this->journalEntries($user, null, $to);
        $rows = $this->flattenLedgerRows($entriesToDate, $accountCode);

        $runningByAccount = [];
        $openingBalance = 0.0;
        $closingBalance = 0.0;
        $periodDebit = 0.0;
        $periodCredit = 0.0;
        $displayRows = [];

        $selectedAccount = $accountCode ? ChartOfAccounts::account($accountCode) : null;

        foreach ($rows as $row) {
            $code = $row['account_code'];
            $account = ChartOfAccounts::account($code);
            $runningByAccount[$code] = $this->applyMovement(
                (float) ($runningByAccount[$code] ?? 0.0),
                (float) $row['debit'],
                (float) $row['credit'],
                $account['normal_balance']
            );

            if ($row['entry_date']->lt($from)) {
                if ($accountCode && $code === $accountCode) {
                    $openingBalance = $runningByAccount[$code];
                }

                continue;
            }

            if ($row['entry_date']->gt($to)) {
                continue;
            }

            $periodDebit += (float) $row['debit'];
            $periodCredit += (float) $row['credit'];

            $displayRows[] = [
                ...$row,
                'running_balance' => $runningByAccount[$code],
                'running_balance_display' => ChartOfAccounts::formatBalance(
                    $runningByAccount[$code],
                    $account['normal_balance']
                ),
            ];
        }

        if ($accountCode && isset($runningByAccount[$accountCode])) {
            $closingBalance = $runningByAccount[$accountCode];
        }

        return [
            'rows' => collect($displayRows),
            'openingBalance' => $openingBalance,
            'closingBalance' => $closingBalance,
            'periodDebit' => $periodDebit,
            'periodCredit' => $periodCredit,
            'account' => $selectedAccount,
            'accounts' => ChartOfAccounts::accounts(),
        ];
    }

    public function trialBalance(User $user, Carbon $asOf): array
    {
        $balances = $this->accountBalances($this->journalEntries($user, null, $asOf));

        $rows = collect(ChartOfAccounts::accounts())
            ->map(function (array $account) use ($balances) {
                $balance = round((float) ($balances[$account['code']] ?? 0.0), 2);
                $columns = ChartOfAccounts::trialBalanceColumns($balance, $account['normal_balance']);

                return [
                    ...$account,
                    'balance' => $balance,
                    'balance_display' => ChartOfAccounts::formatBalance($balance, $account['normal_balance']),
                    'statement_amount' => round(ChartOfAccounts::statementAmount($account, $balance), 2),
                    'debit' => $columns['debit'],
                    'credit' => $columns['credit'],
                ];
            })
            ->filter(fn (array $row) => abs($row['debit']) > 0.004 || abs($row['credit']) > 0.004)
            ->values();

        $totalDebit = round((float) $rows->sum('debit'), 2);
        $totalCredit = round((float) $rows->sum('credit'), 2);

        return [
            'rows' => $rows,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'difference' => round($totalDebit - $totalCredit, 2),
            'accountCount' => $rows->count(),
        ];
    }

    public function profitAndLoss(User $user, Carbon $from, Carbon $to): array
    {
        $balances = $this->accountBalances($this->journalEntries($user, $from, $to));
        $sections = collect(ChartOfAccounts::profitAndLossSections())
            ->map(fn (array $section) => $this->statementSection($section, $balances))
            ->values();

        $salesRevenue = (float) ($sections->firstWhere('key', 'sales_revenue')['total'] ?? 0.0);
        $otherIncome = (float) ($sections->firstWhere('key', 'other_income')['total'] ?? 0.0);
        $costOfSales = (float) ($sections->firstWhere('key', 'cost_of_sales')['total'] ?? 0.0);
        $operatingExpenses = (float) ($sections->firstWhere('key', 'operating_expenses')['total'] ?? 0.0);
        $depreciationExpense = (float) ($sections->firstWhere('key', 'depreciation')['total'] ?? 0.0);
        $stockLosses = (float) ($sections->firstWhere('key', 'stock_losses')['total'] ?? 0.0);

        $grossProfit = round($salesRevenue - $costOfSales, 2);
        $totalExpenses = round($operatingExpenses + $depreciationExpense + $stockLosses, 2);
        $netProfit = round($grossProfit + $otherIncome - $totalExpenses, 2);

        return [
            'sections' => $sections,
            'salesRevenue' => $salesRevenue,
            'otherIncome' => $otherIncome,
            'costOfSales' => $costOfSales,
            'grossProfit' => $grossProfit,
            'operatingExpenses' => $operatingExpenses,
            'depreciationExpense' => $depreciationExpense,
            'stockLosses' => $stockLosses,
            'totalExpenses' => $totalExpenses,
            'netProfit' => $netProfit,
        ];
    }

    public function balanceSheet(User $user, Carbon $asOf): array
    {
        $balances = $this->accountBalances($this->journalEntries($user, null, $asOf));
        $sections = collect(ChartOfAccounts::balanceSheetSections())
            ->map(fn (array $section) => $this->statementSection($section, $balances))
            ->keyBy('key');

        $assetSections = collect([
            $sections->get('current_assets'),
            $sections->get('fixed_assets'),
        ])->filter()->values();

        $liabilitySections = collect([
            $sections->get('current_liabilities'),
        ])->filter()->values();

        $equityRows = collect($sections->get('equity')['rows'] ?? []);
        $currentEarnings = round(
            $this->categoryBalance($balances, 'revenue') - $this->categoryBalance($balances, 'expenditure'),
            2
        );

        if (abs($currentEarnings) > 0.004) {
            $equityRows->push([
                'code' => 'CURR-EARN',
                'name' => 'Current Earnings To Date',
                'category' => 'equity',
                'normal_balance' => 'credit',
                'balance' => $currentEarnings,
                'balance_display' => ChartOfAccounts::formatBalance($currentEarnings, 'credit'),
                'statement_amount' => $currentEarnings,
            ]);
        }

        $totalAssets = round((float) $assetSections->sum('total'), 2);
        $totalLiabilities = round((float) $liabilitySections->sum('total'), 2);
        $totalEquity = round((float) ($sections->get('equity')['total'] ?? 0.0) + $currentEarnings, 2);

        return [
            'assetSections' => $assetSections,
            'liabilitySections' => $liabilitySections,
            'equitySection' => [
                'key' => 'equity',
                'label' => 'Equity',
                'side' => 'equity',
                'rows' => $equityRows->values(),
                'total' => $totalEquity,
            ],
            'totalAssets' => $totalAssets,
            'totalLiabilities' => $totalLiabilities,
            'totalEquity' => $totalEquity,
            'currentEarnings' => $currentEarnings,
            'difference' => round($totalAssets - ($totalLiabilities + $totalEquity), 2),
        ];
    }

    public function paymentVouchers(User $user, Carbon $from, Carbon $to, ?string $method = null): array
    {
        $payments = SupplierPayment::query()
            ->with(['supplier:id,name', 'purchase:id,invoice_number', 'paidByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('payment_date', [$from, $to])
            ->when($method, fn ($query) => $query->where('payment_method', $method))
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (SupplierPayment $payment) {
                return [
                    'id' => $payment->id,
                    'voucher_number' => 'PV-' . str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT),
                    'payment_date' => $payment->payment_date,
                    'supplier_name' => $payment->supplier?->name ?? 'N/A',
                    'invoice_number' => $payment->purchase?->invoice_number ?? 'N/A',
                    'reference_number' => $payment->reference_number,
                    'payment_method' => $payment->payment_method ?: 'Other / Unspecified',
                    'amount' => (float) $payment->amount,
                    'source_label' => $payment->source_label,
                    'paid_by' => $payment->paidByUser?->name ?? 'N/A',
                    'notes' => $payment->notes,
                ];
            });

        return [
            'rows' => $payments,
            'totalAmount' => (float) $payments->sum('amount'),
            'voucherCount' => $payments->count(),
            'suppliersPaid' => $payments->pluck('supplier_name')->filter()->unique()->count(),
            'methodTotals' => $payments
                ->groupBy(fn (array $payment) => $payment['payment_method'])
                ->map(fn (Collection $group) => (float) $group->sum('amount'))
                ->sortDesc()
                ->toArray(),
        ];
    }

    private function saleEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return Sale::query()
            ->with(['items', 'customer:id,name', 'servedByUser:id,name', 'approvedByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->when($from, fn ($query) => $query->whereDate('sale_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('sale_date', '<=', $to->toDateString()))
            ->get()
            ->map(function (Sale $sale) {
                $entryDate = Carbon::parse($sale->sale_date, config('app.timezone'))->startOfDay();

                if ($sale->isOpeningBalanceImport()) {
                    $amount = (float) $sale->total_amount;

                    return $this->entry(
                        'opening_receivable',
                        $entryDate,
                        $sale->invoice_number,
                        'Opening receivable import ' . $sale->invoice_number,
                        [
                            $this->line('11000', $amount, 0.0),
                            $this->line(ChartOfAccounts::openingBalanceEquityCode(), 0.0, $amount),
                        ],
                        [
                            'source_id' => $sale->id,
                            'source_route' => route('customers.receivables'),
                            'party' => $sale->customer?->name ?? 'Walk-in Customer',
                            'entered_by' => $sale->approvedByUser?->name ?? $sale->servedByUser?->name ?? 'N/A',
                            'source_label' => 'Opening Receivable',
                        ]
                    );
                }

                $receivedAmount = min(
                    max((float) $sale->amount_received, (float) $sale->amount_paid),
                    (float) $sale->total_amount
                );
                $receivableAmount = max(0, (float) $sale->total_amount - $receivedAmount);
                $cogsAmount = (float) $sale->items->sum(fn ($item) => (float) $item->purchase_price * (float) $item->quantity);
                $isInsuranceSale = $sale->isInsuranceSale();
                $insuranceReceivableAmount = 0.0;

                if ($isInsuranceSale) {
                    $upfrontAmount = min(max((float) $sale->upfront_amount_paid, 0), (float) $sale->total_amount);
                    $insuranceReceivableAmount = min(max((float) $sale->insurance_covered_amount, 0), (float) $sale->total_amount);
                    $receivableAmount = max(0, round((float) $sale->total_amount - $upfrontAmount - $insuranceReceivableAmount, 2));
                    $receivedAmount = $upfrontAmount;
                }

                $lines = [];

                if ($receivedAmount > 0) {
                    $lines[] = $this->line(
                        ChartOfAccounts::accountCodeForPaymentMethod($sale->payment_method),
                        $receivedAmount,
                        0.0
                    );
                }

                if ($isInsuranceSale && $insuranceReceivableAmount > 0) {
                    $lines[] = $this->line('11100', $insuranceReceivableAmount, 0.0);
                }

                if ($receivableAmount > 0) {
                    $lines[] = $this->line('11000', $receivableAmount, 0.0);
                }

                $lines[] = $this->line(
                    ChartOfAccounts::salesRevenueCode((string) $sale->sale_type),
                    0.0,
                    (float) $sale->total_amount
                );

                if ($cogsAmount > 0) {
                    $lines[] = $this->line('51000', $cogsAmount, 0.0);
                    $lines[] = $this->line('12100', 0.0, $cogsAmount);
                }

                return $this->entry(
                    'sale',
                    $entryDate,
                    $sale->invoice_number,
                    'Sale invoice ' . $sale->invoice_number,
                    $lines,
                    [
                        'source_id' => $sale->id,
                        'source_route' => route('sales.show', $sale),
                        'party' => $sale->customer?->name ?? 'Walk-in Customer',
                        'entered_by' => $sale->servedByUser?->name ?? 'N/A',
                        'source_label' => 'Sale Invoice',
                    ]
                );
              });
    }

    private function insurancePaymentEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return InsurancePayment::query()
            ->with(['sale:id,invoice_number', 'insurer:id,name', 'receivedByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('payment_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('payment_date')
            ->get()
            ->map(function (InsurancePayment $payment) {
                $entryDate = $payment->payment_date instanceof Carbon
                    ? $payment->payment_date->copy()
                    : Carbon::parse($payment->payment_date, config('app.timezone'));
                $amount = (float) $payment->amount;
                $assetCode = ChartOfAccounts::accountCodeForPaymentMethod($payment->payment_method);
                $isReversal = $payment->reversal_of_payment_id !== null;

                $lines = $isReversal
                    ? [
                        $this->line('11100', $amount, 0.0),
                        $this->line($assetCode, 0.0, $amount),
                    ]
                    : [
                        $this->line($assetCode, $amount, 0.0),
                        $this->line('11100', 0.0, $amount),
                    ];

                return $this->entry(
                    'insurance_collection',
                    $entryDate,
                    $payment->reference_number ?: ('INS-' . $payment->id),
                    $isReversal
                        ? 'Insurance remittance reversal for ' . ($payment->sale?->invoice_number ?? 'invoice')
                        : 'Insurance remittance for ' . ($payment->sale?->invoice_number ?? 'invoice'),
                    $lines,
                    [
                        'source_id' => $payment->id,
                        'source_route' => !$payment->sale ? null : route('insurance.claims.show', $payment->sale),
                        'party' => $payment->insurer?->name ?? 'N/A',
                        'entered_by' => $payment->receivedByUser?->name ?? 'N/A',
                        'source_label' => $isReversal ? 'Insurance Reversal' : 'Insurance Remittance',
                    ]
                );
            });
    }

    private function insuranceClaimAdjustmentEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return InsuranceClaimAdjustment::query()
            ->with(['sale:id,invoice_number', 'insurer:id,name', 'createdByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('adjustment_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('adjustment_date')
            ->get()
            ->map(function (InsuranceClaimAdjustment $adjustment) {
                $entryDate = $adjustment->adjustment_date instanceof Carbon
                    ? $adjustment->adjustment_date->copy()
                    : Carbon::parse($adjustment->adjustment_date, config('app.timezone'));
                $amount = (float) $adjustment->amount;

                return $this->entry(
                    'insurance_adjustment',
                    $entryDate,
                    'IADJ-' . $adjustment->id,
                    'Insurance claim write-off for ' . ($adjustment->sale?->invoice_number ?? 'invoice'),
                    [
                        $this->line('50900', $amount, 0.0),
                        $this->line('11100', 0.0, $amount),
                    ],
                    [
                        'source_id' => $adjustment->id,
                        'source_route' => $adjustment->sale ? route('insurance.claims.show', $adjustment->sale) : route('insurance.claims.index'),
                        'party' => $adjustment->insurer?->name ?? 'N/A',
                        'entered_by' => $adjustment->createdByUser?->name ?? 'N/A',
                        'source_label' => 'Insurance Adjustment',
                        'note' => $adjustment->reason,
                    ]
                );
            });
    }

    private function customerPaymentEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return Payment::query()
            ->with(['sale:id,invoice_number', 'customer:id,name', 'receivedByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('payment_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('payment_date')
            ->get()
            ->map(function (Payment $payment) {
                $entryDate = $payment->payment_date instanceof Carbon
                    ? $payment->payment_date->copy()
                    : Carbon::parse($payment->payment_date, config('app.timezone'));
                $amount = (float) $payment->amount;
                $assetCode = ChartOfAccounts::accountCodeForPaymentMethod($payment->payment_method);
                $isReversal = $payment->reversal_of_payment_id !== null;

                $lines = $isReversal
                    ? [
                        $this->line('11000', $amount, 0.0),
                        $this->line($assetCode, 0.0, $amount),
                    ]
                    : [
                        $this->line($assetCode, $amount, 0.0),
                        $this->line('11000', 0.0, $amount),
                    ];

                return $this->entry(
                    'customer_collection',
                    $entryDate,
                    $payment->reference_number ?: ('COL-' . $payment->id),
                    $isReversal
                        ? 'Customer payment reversal for ' . ($payment->sale?->invoice_number ?? 'invoice')
                        : 'Customer collection for ' . ($payment->sale?->invoice_number ?? 'invoice'),
                    $lines,
                    [
                        'source_id' => $payment->id,
                        'source_route' => !$payment->sale
                            ? null
                            : ($payment->sale->isOpeningBalanceImport()
                                ? route('customers.collections.index')
                                : route('sales.show', $payment->sale)),
                        'party' => $payment->customer?->name ?? 'N/A',
                        'entered_by' => $payment->receivedByUser?->name ?? 'N/A',
                        'source_label' => $isReversal ? 'Collection Reversal' : 'Customer Collection',
                    ]
                );
            });
    }

    private function purchaseEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return Purchase::query()
            ->with(['supplier:id,name', 'createdByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->when($from, fn ($query) => $query->whereDate('purchase_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('purchase_date', '<=', $to->toDateString()))
            ->get()
            ->map(function (Purchase $purchase) {
                $entryDate = Carbon::parse($purchase->purchase_date, config('app.timezone'))->startOfDay();
                $amount = (float) $purchase->total_amount;

                if ($purchase->isOpeningBalanceImport()) {
                    return $this->entry(
                        'opening_payable',
                        $entryDate,
                        $purchase->invoice_number,
                        'Opening payable import ' . $purchase->invoice_number,
                        [
                            $this->line(ChartOfAccounts::openingBalanceEquityCode(), $amount, 0.0),
                            $this->line('21000', 0.0, $amount),
                        ],
                        [
                            'source_id' => $purchase->id,
                            'source_route' => route('suppliers.payables'),
                            'party' => $purchase->supplier?->name ?? 'N/A',
                            'entered_by' => $purchase->createdByUser?->name ?? 'N/A',
                            'source_label' => 'Opening Payable',
                        ]
                    );
                }

                return $this->entry(
                    'purchase',
                    $entryDate,
                    $purchase->invoice_number,
                    'Purchase invoice ' . $purchase->invoice_number,
                    [
                        $this->line('12100', $amount, 0.0),
                        $this->line('21000', 0.0, $amount),
                    ],
                    [
                        'source_id' => $purchase->id,
                        'source_route' => route('purchases.show', $purchase),
                        'party' => $purchase->supplier?->name ?? 'N/A',
                        'entered_by' => $purchase->createdByUser?->name ?? 'N/A',
                        'source_label' => 'Purchase Invoice',
                    ]
                );
            });
    }

    private function supplierPaymentEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return SupplierPayment::query()
            ->with(['purchase:id,invoice_number', 'supplier:id,name', 'paidByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('payment_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('payment_date')
            ->get()
            ->map(function (SupplierPayment $payment) {
                $entryDate = $payment->payment_date instanceof Carbon
                    ? $payment->payment_date->copy()
                    : Carbon::parse($payment->payment_date, config('app.timezone'));
                $amount = (float) $payment->amount;

                return $this->entry(
                    'supplier_payment',
                    $entryDate,
                    $payment->reference_number ?: ('SUP-' . $payment->id),
                    'Supplier payment for ' . ($payment->purchase?->invoice_number ?? 'invoice'),
                    [
                        $this->line('21000', $amount, 0.0),
                        $this->line(ChartOfAccounts::accountCodeForPaymentMethod($payment->payment_method), 0.0, $amount),
                    ],
                    [
                        'source_id' => $payment->id,
                        'source_route' => !$payment->purchase
                            ? null
                            : ($payment->purchase->isOpeningBalanceImport()
                                ? route('suppliers.payments.index')
                                : route('purchases.show', $payment->purchase)),
                        'party' => $payment->supplier?->name ?? 'N/A',
                        'entered_by' => $payment->paidByUser?->name ?? 'N/A',
                        'source_label' => 'Payment Voucher',
                    ]
                );
            });
    }

    private function stockAdjustmentEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return StockAdjustment::query()
            ->with([
                'product:id,name',
                'purchase:id,invoice_number',
                'adjustedByUser:id,name',
                'batch:id,purchase_price,purchase_item_id',
                'batch.purchaseItem:id,unit_cost',
            ])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->whereBetween('adjustment_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('adjustment_date')
            ->get()
            ->map(function (StockAdjustment $adjustment) {
                $entryDate = $adjustment->adjustment_date instanceof Carbon
                    ? $adjustment->adjustment_date->copy()
                    : Carbon::parse($adjustment->adjustment_date, config('app.timezone'));

                $unitCost = $this->resolveBatchCost($adjustment->batch);
                $amount = round($unitCost * (float) $adjustment->quantity, 2);
                $adjustmentAccount = ChartOfAccounts::stockAdjustmentAccountCode(
                    (string) $adjustment->direction,
                    $adjustment->reason
                );

                $lines = $adjustment->direction === 'increase'
                    ? [
                        $this->line('12100', $amount, 0.0),
                        $this->line($adjustmentAccount, 0.0, $amount),
                    ]
                    : [
                        $this->line($adjustmentAccount, $amount, 0.0),
                        $this->line('12100', 0.0, $amount),
                    ];

                return $this->entry(
                    'stock_adjustment',
                    $entryDate,
                    'ADJ-' . str_pad((string) $adjustment->id, 5, '0', STR_PAD_LEFT),
                    ucfirst((string) $adjustment->direction) . ' adjustment for ' . ($adjustment->product?->name ?? 'stock'),
                    $lines,
                    [
                        'source_id' => $adjustment->id,
                        'source_route' => route('stock.index'),
                        'party' => $adjustment->product?->name ?? 'N/A',
                        'entered_by' => $adjustment->adjustedByUser?->name ?? 'N/A',
                        'source_label' => 'Stock Adjustment',
                        'note' => $adjustment->note,
                    ]
                );
            });
    }

    private function expenseEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return AccountingExpense::query()
            ->with(['enteredByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->whereBetween('expense_date', [
                $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone')),
                $to ?? Carbon::now(config('app.timezone'))->endOfDay(),
            ])
            ->orderBy('expense_date')
            ->get()
            ->map(function (AccountingExpense $expense) {
                $entryDate = $expense->expense_date instanceof Carbon
                    ? $expense->expense_date->copy()
                    : Carbon::parse($expense->expense_date, config('app.timezone'));
                $amount = (float) $expense->amount;

                return $this->entry(
                    'expense',
                    $entryDate,
                    $expense->reference_number ?: ('EXP-' . $expense->id),
                    $expense->description,
                    [
                        $this->line($expense->account_code, $amount, 0.0),
                        $this->line(ChartOfAccounts::accountCodeForPaymentMethod($expense->payment_method), 0.0, $amount),
                    ],
                    [
                        'source_id' => $expense->id,
                        'source_route' => route('accounting.expenses.index'),
                        'party' => $expense->payee_name ?: 'N/A',
                        'entered_by' => $expense->enteredByUser?->name ?? 'N/A',
                        'source_label' => 'Expense',
                        'note' => $expense->notes,
                    ]
                );
            });
    }

    private function fixedAssetAcquisitionEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        return FixedAsset::query()
            ->with(['enteredByUser:id,name'])
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->when($from, fn ($query) => $query->whereDate('acquisition_date', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('acquisition_date', '<=', $to->toDateString()))
            ->orderBy('acquisition_date')
            ->get()
            ->map(function (FixedAsset $asset) {
                $entryDate = Carbon::parse($asset->acquisition_date, config('app.timezone'))->startOfDay();
                $amount = (float) $asset->acquisition_cost;

                return $this->entry(
                    'fixed_asset',
                    $entryDate,
                    $asset->reference_number ?: ($asset->asset_code ?: ('FA-' . $asset->id)),
                    'Fixed asset acquisition - ' . $asset->asset_name,
                    [
                        $this->line($asset->assetAccountCode(), $amount, 0.0),
                        $this->line(ChartOfAccounts::accountCodeForPaymentMethod($asset->payment_method), 0.0, $amount),
                    ],
                    [
                        'source_id' => $asset->id,
                        'source_route' => route('accounting.fixed-assets.index'),
                        'party' => $asset->vendor_name ?: 'N/A',
                        'entered_by' => $asset->enteredByUser?->name ?? 'N/A',
                        'source_label' => 'Fixed Asset',
                        'note' => $asset->notes,
                    ]
                );
            });
    }

    private function fixedAssetDepreciationEntries(User $user, ?Carbon $from, ?Carbon $to): Collection
    {
        $periodStart = $from ?? Carbon::create(2000, 1, 1, 0, 0, 0, config('app.timezone'));
        $periodEnd = $to ?? Carbon::now(config('app.timezone'))->endOfDay();

        return FixedAsset::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->whereDate('acquisition_date', '<=', $periodEnd->toDateString())
            ->get()
            ->flatMap(function (FixedAsset $asset) use ($periodStart, $periodEnd) {
                $monthly = $asset->monthlyDepreciationAmount();

                if ($monthly <= 0) {
                    return collect();
                }

                $acquiredMonth = Carbon::parse($asset->acquisition_date, config('app.timezone'))->startOfMonth();
                $startMonth = $acquiredMonth->copy()->addMonth();
                $endMonth = $periodEnd->copy()->startOfMonth();

                if ($startMonth->gt($endMonth)) {
                    return collect();
                }

                $months = collect();
                $life = max(0, (int) $asset->useful_life_months);

                for ($i = 0; $i < $life; $i++) {
                    $monthDate = $startMonth->copy()->addMonths($i);

                    if ($monthDate->gt($endMonth)) {
                        break;
                    }

                    $entryDate = $monthDate->copy()->endOfMonth();

                    if ($entryDate->lt($periodStart) || $entryDate->gt($periodEnd)) {
                        continue;
                    }

                    $months->push($this->entry(
                        'depreciation',
                        $entryDate,
                        'DEP-' . $asset->id . '-' . $monthDate->format('Ym'),
                        'Monthly depreciation - ' . $asset->asset_name,
                        [
                            $this->line('50800', $monthly, 0.0),
                            $this->line($asset->accumulatedDepreciationAccountCode(), 0.0, $monthly),
                        ],
                        [
                            'source_id' => $asset->id,
                            'source_route' => route('accounting.fixed-assets.index'),
                            'party' => $asset->asset_name,
                            'entered_by' => $asset->enteredByUser?->name ?? 'N/A',
                            'source_label' => 'Depreciation',
                        ]
                    ));
                }

                return $months;
            })
            ->values();
    }

    private function flattenLedgerRows(Collection $entries, ?string $accountCode = null): Collection
    {
        return $entries
            ->flatMap(function (array $entry) {
                return collect($entry['lines'])->map(function (array $line) use ($entry) {
                    return [
                        'entry_date' => $entry['entry_date'],
                        'display_date' => $entry['display_date'],
                        'sort_stamp' => $entry['sort_stamp'],
                        'source_type' => $entry['source_type'],
                        'source_label' => $entry['source_label'],
                        'reference_number' => $entry['reference_number'],
                        'description' => $entry['description'],
                        'party' => $entry['party'],
                        'entered_by' => $entry['entered_by'],
                        'source_route' => $entry['source_route'],
                        ...$line,
                    ];
                });
            })
            ->when($accountCode, fn (Collection $rows) => $rows->where('account_code', $accountCode))
            ->sortBy('sort_stamp')
            ->values();
    }

    private function accountBalances(Collection $entries): array
    {
        $balances = [];

        foreach (ChartOfAccounts::accounts() as $account) {
            $balances[$account['code']] = 0.0;
        }

        foreach ($entries as $entry) {
            foreach ($entry['lines'] as $line) {
                $balances[$line['account_code']] = $this->applyMovement(
                    (float) ($balances[$line['account_code']] ?? 0.0),
                    (float) $line['debit'],
                    (float) $line['credit'],
                    $line['normal_balance']
                );
            }
        }

        return $balances;
    }

    private function periodAccountTotal(Collection $entries, array $accountCodes, string $side): float
    {
        return round($entries
            ->flatMap(fn (array $entry) => $entry['lines'])
            ->filter(fn (array $line) => in_array($line['account_code'], $accountCodes, true))
            ->sum($side), 2);
    }

    private function periodEntryTotal(Collection $entries, string $sourceType, string $side): float
    {
        return round($entries
            ->filter(fn (array $entry) => $entry['source_type'] === $sourceType)
            ->flatMap(fn (array $entry) => $entry['lines'])
            ->sum($side), 2);
    }

    private function balanceTotal(array $balances, array $codes): float
    {
        return round(collect($codes)->sum(function (string $code) use ($balances) {
            $account = ChartOfAccounts::account($code);

            return ChartOfAccounts::statementAmount($account, (float) ($balances[$code] ?? 0.0));
        }), 2);
    }

    private function categoryBalance(array $balances, string $category): float
    {
        return round(collect(ChartOfAccounts::accounts())
            ->filter(fn (array $account) => $account['category'] === $category)
            ->sum(fn (array $account) => ChartOfAccounts::statementAmount(
                $account,
                (float) ($balances[$account['code']] ?? 0.0)
            )), 2);
    }

    private function statementSection(array $definition, array $balances): array
    {
        $rows = collect($definition['codes'])
            ->map(function (string $code) use ($balances) {
                $account = ChartOfAccounts::account($code);
                $balance = round((float) ($balances[$code] ?? 0.0), 2);
                $amount = round(ChartOfAccounts::statementAmount($account, $balance), 2);

                return [
                    ...$account,
                    'balance' => $balance,
                    'balance_display' => ChartOfAccounts::formatBalance($balance, $account['normal_balance']),
                    'statement_amount' => $amount,
                ];
            })
            ->filter(fn (array $row) => abs($row['statement_amount']) > 0.004)
            ->values();

        return [
            ...$definition,
            'rows' => $rows,
            'total' => round((float) $rows->sum('statement_amount'), 2),
        ];
    }

    private function resolveBatchCost(?ProductBatch $batch): float
    {
        if (!$batch) {
            return 0.0;
        }

        if ((float) $batch->purchase_price > 0) {
            return (float) $batch->purchase_price;
        }

        return (float) ($batch->purchaseItem?->unit_cost ?? 0.0);
    }

    private function applyMovement(float $runningBalance, float $debit, float $credit, string $normalBalance): float
    {
        return $runningBalance + ($normalBalance === 'credit'
            ? ($credit - $debit)
            : ($debit - $credit));
    }

    private function line(string $accountCode, float $debit, float $credit): array
    {
        $account = ChartOfAccounts::account($accountCode);

        return [
            'account_code' => $account['code'],
            'account_name' => $account['name'],
            'category' => $account['category'],
            'normal_balance' => $account['normal_balance'],
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
        ];
    }

    private function entry(
        string $sourceType,
        Carbon $entryDate,
        string $referenceNumber,
        string $description,
        array $lines,
        array $meta = []
    ): array {
        $lineCollection = collect($lines);

        return [
            'source_type' => $sourceType,
            'source_label' => $meta['source_label'] ?? ucwords(str_replace('_', ' ', $sourceType)),
            'source_id' => $meta['source_id'] ?? null,
            'source_route' => $meta['source_route'] ?? null,
            'entry_date' => $entryDate,
            'display_date' => $entryDate->format($entryDate->hour === 0 && $entryDate->minute === 0 ? 'd M Y' : 'd M Y H:i'),
            'sort_stamp' => sprintf('%015d-%s-%010d', $entryDate->getTimestamp(), $sourceType, (int) ($meta['source_id'] ?? 0)),
            'reference_number' => $referenceNumber,
            'description' => $description,
            'party' => $meta['party'] ?? 'N/A',
            'entered_by' => $meta['entered_by'] ?? 'N/A',
            'note' => $meta['note'] ?? null,
            'lines' => $lineCollection->values()->all(),
            'debit_total' => round((float) $lineCollection->sum('debit'), 2),
            'credit_total' => round((float) $lineCollection->sum('credit'), 2),
        ];
    }
}
