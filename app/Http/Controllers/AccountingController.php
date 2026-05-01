<?php

namespace App\Http\Controllers;

use App\Models\AccountingExpense;
use App\Models\FixedAsset;
use App\Support\Accounting\AccountingLedgerService;
use App\Support\Accounting\ChartOfAccounts;
use App\Support\Printing\CsvDownload;
use App\Support\Printing\DocumentBranding;
use App\Support\Printing\PdfDownload;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    public function __construct(
        private readonly AccountingLedgerService $ledgerService
    ) {
    }

    public function index(Request $request)
    {
        return view('accounting.index', $this->indexPayload($request));
    }

    public function chartOfAccounts(Request $request)
    {
        return view('accounting.chart-of-accounts', $this->chartPayload($request));
    }

    public function printChartOfAccounts(Request $request)
    {
        $data = $this->chartPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.chart-of-accounts', $data);
    }

    public function downloadChartOfAccounts(Request $request)
    {
        $data = $this->chartPayload($request);
        $rows = [];

        foreach ($data['groupedAccounts'] as $categoryKey => $accounts) {
            if ($data['selectedCategory'] !== '' && $data['selectedCategory'] !== $categoryKey) {
                continue;
            }

            $category = $data['categories'][$categoryKey] ?? [
                'label' => ucwords(str_replace('_', ' ', (string) $categoryKey)),
            ];

            $rows[] = [$category['label']];
            $rows[] = ['Code', 'Account', 'Normal Balance', 'Statement Amount', 'Balance Display'];

            foreach ($accounts as $account) {
                $rows[] = [
                    $account['code'],
                    $account['name'],
                    ucfirst($account['normal_balance']),
                    ChartOfAccounts::statementAmount($account, (float) ($account['balance'] ?? 0.0)),
                    $account['balance_display'],
                ];
            }

            $rows[] = [];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'chart-of-accounts',
            'prints.accounting.chart-of-accounts',
            $data,
            $rows
        );
    }

    public function generalLedger(Request $request)
    {
        return view('accounting.general-ledger', $this->generalLedgerPayload($request));
    }

    public function printGeneralLedger(Request $request)
    {
        $data = $this->generalLedgerPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.general-ledger', $data);
    }

    public function downloadGeneralLedger(Request $request)
    {
        $data = $this->generalLedgerPayload($request);
        $rows = [['Date', 'Account Code', 'Account Name', 'Reference', 'Description', 'Debit', 'Credit', 'Running Balance']];

        foreach ($data['ledger']['rows'] as $row) {
            $rows[] = [
                $row['display_date'],
                $row['account_code'],
                $row['account_name'],
                $row['reference_number'],
                $row['description'],
                (float) $row['debit'],
                (float) $row['credit'],
                $row['running_balance_display'],
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'general-ledger',
            'prints.accounting.general-ledger',
            $data,
            $rows,
            'a4',
            'landscape'
        );
    }

    public function trialBalance(Request $request)
    {
        return view('accounting.trial-balance', $this->trialBalancePayload($request));
    }

    public function printTrialBalance(Request $request)
    {
        $data = $this->trialBalancePayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.trial-balance', $data);
    }

    public function downloadTrialBalance(Request $request)
    {
        $data = $this->trialBalancePayload($request);
        $rows = [['Code', 'Account', 'Category', 'Balance', 'Debit', 'Credit']];

        foreach ($data['trialBalance']['rows'] as $row) {
            $rows[] = [
                $row['code'],
                $row['name'],
                ucfirst($row['category']),
                $row['balance_display'],
                (float) $row['debit'],
                (float) $row['credit'],
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'trial-balance',
            'prints.accounting.trial-balance',
            $data,
            $rows
        );
    }

    public function profitAndLoss(Request $request)
    {
        return view('accounting.profit-loss', $this->profitLossPayload($request));
    }

    public function printProfitAndLoss(Request $request)
    {
        $data = $this->profitLossPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.profit-loss', $data);
    }

    public function downloadProfitAndLoss(Request $request)
    {
        $data = $this->profitLossPayload($request);
        $rows = [['Section', 'Code', 'Account', 'Amount']];

        foreach ($data['statement']['sections'] as $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = [
                    $section['label'],
                    $row['code'],
                    $row['name'],
                    (float) $row['statement_amount'],
                ];
            }
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'profit-loss',
            'prints.accounting.profit-loss',
            $data,
            $rows
        );
    }

    public function balanceSheet(Request $request)
    {
        return view('accounting.balance-sheet', $this->balanceSheetPayload($request));
    }

    public function printBalanceSheet(Request $request)
    {
        $data = $this->balanceSheetPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.balance-sheet', $data);
    }

    public function downloadBalanceSheet(Request $request)
    {
        $data = $this->balanceSheetPayload($request);
        $rows = [['Section', 'Code', 'Account', 'Amount', 'Balance Display']];

        foreach ($data['statement']['assetSections'] as $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = [$section['label'], $row['code'], $row['name'], (float) $row['statement_amount'], $row['balance_display']];
            }
        }

        foreach ($data['statement']['liabilitySections'] as $section) {
            foreach ($section['rows'] as $row) {
                $rows[] = [$section['label'], $row['code'], $row['name'], (float) $row['statement_amount'], $row['balance_display']];
            }
        }

        foreach ($data['statement']['equitySection']['rows'] as $row) {
            $rows[] = [
                $data['statement']['equitySection']['label'],
                $row['code'],
                $row['name'],
                (float) $row['statement_amount'],
                $row['balance_display'],
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'balance-sheet',
            'prints.accounting.balance-sheet',
            $data,
            $rows
        );
    }

    public function journals(Request $request)
    {
        return view('accounting.journals', $this->journalsPayload($request));
    }

    public function printJournals(Request $request)
    {
        $data = $this->journalsPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.journals', $data);
    }

    public function downloadJournals(Request $request)
    {
        $data = $this->journalsPayload($request);
        $rows = [['Journal Reference', 'Date', 'Source', 'Party', 'Entered By', 'Account Code', 'Account Name', 'Debit', 'Credit', 'Description']];

        foreach ($data['entries'] as $entry) {
            foreach ($entry['lines'] as $line) {
                $rows[] = [
                    $entry['reference_number'],
                    $entry['display_date'],
                    $entry['source_label'],
                    $entry['party'],
                    $entry['entered_by'],
                    $line['account_code'],
                    $line['account_name'],
                    (float) $line['debit'],
                    (float) $line['credit'],
                    $entry['description'],
                ];
            }
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'journals',
            'prints.accounting.journals',
            $data,
            $rows,
            'a4',
            'landscape'
        );
    }

    public function paymentVouchers(Request $request)
    {
        return view('accounting.payment-vouchers', $this->paymentVouchersPayload($request));
    }

    public function printPaymentVouchers(Request $request)
    {
        $data = $this->paymentVouchersPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.payment-vouchers', $data);
    }

    public function downloadPaymentVouchers(Request $request)
    {
        $data = $this->paymentVouchersPayload($request);
        $rows = [['Voucher', 'Date', 'Supplier', 'Invoice', 'Method', 'Paid By', 'Amount']];

        foreach ($data['vouchers']['rows'] as $voucher) {
            $rows[] = [
                $voucher['voucher_number'],
                optional($voucher['payment_date'])->format('Y-m-d H:i'),
                $voucher['supplier_name'],
                $voucher['invoice_number'],
                $voucher['payment_method'],
                $voucher['paid_by'],
                (float) $voucher['amount'],
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'payment-vouchers',
            'prints.accounting.payment-vouchers',
            $data,
            $rows,
            'a4',
            'landscape'
        );
    }

    public function expensesIndex(Request $request)
    {
        return view('accounting.expenses.index', $this->expensesPayload($request));
    }

    public function printExpenses(Request $request)
    {
        $data = $this->expensesPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.expenses', $data);
    }

    public function downloadExpenses(Request $request)
    {
        $data = $this->expensesPayload($request);
        $rows = [['Date', 'Account', 'Payee', 'Method', 'Reference', 'Description', 'Amount', 'Entered By']];

        foreach ($data['expenses'] as $expense) {
            $rows[] = [
                optional($expense->expense_date)->format('Y-m-d H:i'),
                $expense->account_code,
                $expense->payee_name ?? 'N/A',
                $expense->payment_method,
                $expense->reference_number ?? 'N/A',
                $expense->description,
                (float) $expense->amount,
                $expense->enteredByUser?->name ?? 'System',
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'expenses',
            'prints.accounting.expenses',
            $data,
            $rows,
            'a4',
            'landscape'
        );
    }

    public function createExpense(Request $request)
    {
        return view('accounting.expenses.create', [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'expenseAccounts' => ChartOfAccounts::manualExpenseAccounts(),
            'paymentMethods' => $this->paymentMethods(),
            'navRoute' => 'accounting.expenses.index',
        ]);
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'account_code' => ['required', 'string', 'max:10'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payee_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $allowedCodes = collect(ChartOfAccounts::manualExpenseAccounts())->pluck('code');

        if (!$allowedCodes->contains($validated['account_code'])) {
            return back()
                ->withInput()
                ->withErrors(['account_code' => 'Choose a valid manual expense account.']);
        }

        AccountingExpense::create([
            'client_id' => $request->user()->client_id,
            'branch_id' => $request->user()->branch_id,
            'account_code' => $validated['account_code'],
            'expense_date' => Carbon::parse($validated['expense_date'], config('app.timezone'))->endOfDay(),
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payee_name' => $validated['payee_name'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'description' => $validated['description'],
            'notes' => $validated['notes'] ?? null,
            'entered_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return redirect()
            ->route('accounting.expenses.index')
            ->with('success', 'Expense posted to accounting successfully.');
    }

    public function fixedAssetsIndex(Request $request)
    {
        return view('accounting.fixed-assets.index', $this->fixedAssetsPayload($request));
    }

    public function printFixedAssets(Request $request)
    {
        $data = $this->fixedAssetsPayload($request);
        $data['branding'] = DocumentBranding::forUser($request->user());
        $data['autoPrint'] = $request->boolean('autoprint', true);

        return view('prints.accounting.fixed-assets', $data);
    }

    public function downloadFixedAssets(Request $request)
    {
        $data = $this->fixedAssetsPayload($request);
        $rows = [['Asset', 'Category', 'Acquired', 'Cost', 'Salvage', 'Useful Life Months', 'Monthly Depreciation', 'Accumulated Depreciation', 'Net Book Value']];

        foreach ($data['assets'] as $asset) {
            $model = $asset['model'];
            $rows[] = [
                $model->asset_name,
                $asset['definition']['label'],
                optional($model->acquisition_date)->format('Y-m-d'),
                (float) $model->acquisition_cost,
                (float) $model->salvage_value,
                (int) $model->useful_life_months,
                (float) $asset['monthly_depreciation'],
                (float) $asset['accumulated_depreciation'],
                (float) $asset['net_book_value'],
            ];
        }

        return $this->downloadAsCsvOrPdf(
            $request,
            'fixed-assets',
            'prints.accounting.fixed-assets',
            $data,
            $rows,
            'a4',
            'landscape'
        );
    }

    public function createFixedAsset(Request $request)
    {
        return view('accounting.fixed-assets.create', [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'fixedAssetDefinitions' => ChartOfAccounts::fixedAssetDefinitions(),
            'paymentMethods' => $this->paymentMethods(),
            'navRoute' => 'accounting.fixed-assets.index',
        ]);
    }

    public function storeFixedAsset(Request $request)
    {
        $validated = $request->validate([
            'asset_name' => ['required', 'string', 'max:255'],
            'asset_category' => ['required', 'string', 'max:40'],
            'asset_code' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'gte:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'payment_method' => ['required', 'string', 'max:50'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        if (!array_key_exists($validated['asset_category'], ChartOfAccounts::fixedAssetDefinitions())) {
            return back()
                ->withInput()
                ->withErrors(['asset_category' => 'Choose a valid fixed asset category.']);
        }

        $salvage = (float) ($validated['salvage_value'] ?? 0);
        $cost = (float) $validated['acquisition_cost'];

        if ($salvage > $cost) {
            return back()
                ->withInput()
                ->withErrors(['salvage_value' => 'Salvage value cannot be higher than acquisition cost.']);
        }

        FixedAsset::create([
            'client_id' => $request->user()->client_id,
            'branch_id' => $request->user()->branch_id,
            'asset_name' => $validated['asset_name'],
            'asset_category' => $validated['asset_category'],
            'asset_code' => $validated['asset_code'] ?? null,
            'acquisition_date' => $validated['acquisition_date'],
            'acquisition_cost' => $cost,
            'salvage_value' => $salvage,
            'useful_life_months' => $validated['useful_life_months'],
            'payment_method' => $validated['payment_method'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'entered_by' => $request->user()->id,
            'is_active' => true,
        ]);

        return redirect()
            ->route('accounting.fixed-assets.index')
            ->with('success', 'Fixed asset added to accounting successfully.');
    }

    private function indexPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'summary' => $this->ledgerService->buildHomeSummary($request->user(), $from, $to),
            'navRoute' => 'accounting.index',
        ];
    }

    private function chartPayload(Request $request): array
    {
        [, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        $selectedCategory = $request->string('category')->toString();
        $chartData = $this->ledgerService->chartData($request->user(), $to);

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'categories' => ChartOfAccounts::categoryDefinitions(),
            'groupedAccounts' => $chartData['groupedAccounts'],
            'selectedCategory' => $selectedCategory,
            'asOfDate' => $to,
            'navRoute' => 'accounting.chart',
        ];
    }

    private function generalLedgerPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        $accountCode = $request->string('account')->toString() ?: null;

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'accountCode' => $accountCode,
            'ledger' => $this->ledgerService->generalLedger($request->user(), $from, $to, $accountCode),
            'navRoute' => 'accounting.general-ledger',
        ];
    }

    private function trialBalancePayload(Request $request): array
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->string('as_of')->toString(), config('app.timezone'))->endOfDay()
            : Carbon::today(config('app.timezone'))->endOfDay();

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'asOf' => $asOf,
            'trialBalance' => $this->ledgerService->trialBalance($request->user(), $asOf),
            'navRoute' => 'accounting.trial-balance',
        ];
    }

    private function profitLossPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'statement' => $this->ledgerService->profitAndLoss($request->user(), $from, $to),
            'navRoute' => 'accounting.profit-loss',
        ];
    }

    private function balanceSheetPayload(Request $request): array
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->string('as_of')->toString(), config('app.timezone'))->endOfDay()
            : Carbon::today(config('app.timezone'))->endOfDay();

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'asOf' => $asOf,
            'statement' => $this->ledgerService->balanceSheet($request->user(), $asOf),
            'navRoute' => 'accounting.balance-sheet',
        ];
    }

    private function journalsPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        $search = trim($request->string('search')->toString());
        $entries = $this->ledgerService
            ->journalEntries($request->user(), $from, $to)
            ->sortByDesc('sort_stamp')
            ->values();

        if ($search !== '') {
            $entries = $entries
                ->filter(function (array $entry) use ($search) {
                    $haystack = implode(' ', [
                        $entry['reference_number'],
                        $entry['description'],
                        $entry['party'],
                        $entry['entered_by'],
                    ]);

                    return str_contains(strtolower($haystack), strtolower($search));
                })
                ->values();
        }

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'entries' => $entries,
            'navRoute' => 'accounting.journals',
        ];
    }

    private function paymentVouchersPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        $method = trim($request->string('method')->toString()) ?: null;

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'method' => $method,
            'vouchers' => $this->ledgerService->paymentVouchers($request->user(), $from, $to, $method),
            'navRoute' => 'accounting.vouchers',
        ];
    }

    private function expensesPayload(Request $request): array
    {
        [$from, $to] = $this->ledgerService->resolveDateRange(
            $request->string('from')->toString(),
            $request->string('to')->toString()
        );

        $accountCode = trim($request->string('account')->toString()) ?: null;
        $expenses = AccountingExpense::query()
            ->with(['enteredByUser:id,name'])
            ->where('client_id', $request->user()->client_id)
            ->where('branch_id', $request->user()->branch_id)
            ->where('is_active', true)
            ->whereBetween('expense_date', [$from, $to])
            ->when($accountCode, fn ($query) => $query->where('account_code', $accountCode))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->get();

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'from' => $from,
            'to' => $to,
            'accountCode' => $accountCode,
            'expenses' => $expenses,
            'expenseAccounts' => ChartOfAccounts::manualExpenseAccounts(),
            'navRoute' => 'accounting.expenses.index',
        ];
    }

    private function fixedAssetsPayload(Request $request): array
    {
        $asOf = $request->filled('as_of')
            ? Carbon::parse($request->string('as_of')->toString(), config('app.timezone'))->endOfDay()
            : Carbon::today(config('app.timezone'))->endOfDay();

        $category = trim($request->string('category')->toString()) ?: null;
        $assets = FixedAsset::query()
            ->with(['enteredByUser:id,name'])
            ->where('client_id', $request->user()->client_id)
            ->where('branch_id', $request->user()->branch_id)
            ->where('is_active', true)
            ->when($category, fn ($query) => $query->where('asset_category', $category))
            ->orderByDesc('acquisition_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (FixedAsset $asset) use ($asOf) {
                $monthsElapsed = $asset->depreciationMonthsElapsed($asOf);

                return [
                    'model' => $asset,
                    'definition' => $asset->categoryDefinition(),
                    'depreciable_base' => round(max(0, (float) $asset->acquisition_cost - (float) $asset->salvage_value), 2),
                    'monthly_depreciation' => $asset->monthlyDepreciationAmount(),
                    'accumulated_depreciation' => $asset->accumulatedDepreciation($asOf),
                    'net_book_value' => $asset->netBookValue($asOf),
                    'months_elapsed' => $monthsElapsed,
                    'months_remaining' => max(0, (int) $asset->useful_life_months - $monthsElapsed),
                ];
            });

        $categoryBreakdown = $assets
            ->groupBy(fn (array $row) => $row['definition']['label'])
            ->map(function ($group, $label) {
                return [
                    'label' => $label,
                    'asset_count' => $group->count(),
                    'cost' => round((float) $group->sum(fn (array $row) => (float) $row['model']->acquisition_cost), 2),
                    'accumulated_depreciation' => round((float) $group->sum('accumulated_depreciation'), 2),
                    'net_book_value' => round((float) $group->sum('net_book_value'), 2),
                ];
            })
            ->sortByDesc('net_book_value')
            ->values();

        return [
            'clientName' => optional($request->user()->client)->name ?? 'N/A',
            'branchName' => optional($request->user()->branch)->name ?? 'N/A',
            'asOf' => $asOf,
            'category' => $category,
            'assets' => $assets,
            'categoryBreakdown' => $categoryBreakdown,
            'fixedAssetDefinitions' => ChartOfAccounts::fixedAssetDefinitions(),
            'navRoute' => 'accounting.fixed-assets.index',
        ];
    }

    private function downloadAsCsvOrPdf(
        Request $request,
        string $fileBase,
        string $pdfView,
        array $pdfData,
        array $csvRows,
        string $paper = 'a4',
        string $orientation = 'portrait'
    ) {
        $timestamp = now()->format('Ymd-His');

        if ($this->downloadFormat($request) === 'pdf') {
            $pdfData['branding'] = DocumentBranding::forUser($request->user());

            return PdfDownload::make(
                $fileBase . '-' . $timestamp . '.pdf',
                $pdfView,
                $pdfData,
                $paper,
                $orientation
            );
        }

        return CsvDownload::make(
            $fileBase . '-' . $timestamp . '.csv',
            [],
            $csvRows
        );
    }

    private function downloadFormat(Request $request): string
    {
        return strtolower(trim($request->string('format')->toString())) === 'pdf'
            ? 'pdf'
            : 'csv';
    }

    private function paymentMethods(): array
    {
        return [
            'Cash' => 'Cash',
            'Bulky Cash' => 'Bulky Cash',
            'Petty Cash' => 'Petty Cash',
            'MTN' => 'MTN',
            'Airtel' => 'Airtel',
            'Bank' => 'Bank',
            'Cheque' => 'Cheque',
        ];
    }
}

