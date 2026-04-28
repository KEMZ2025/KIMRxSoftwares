<?php

namespace App\Support\Accounting;

use App\Support\PaymentMethodBuckets;

class ChartOfAccounts
{
    public static function categoryDefinitions(): array
    {
        return [
            'assets' => [
                'key' => 'assets',
                'label' => 'Assets',
                'tone' => 'blue',
                'description' => 'Cash, mobile money, receivables, and inventory balances.',
            ],
            'liabilities' => [
                'key' => 'liabilities',
                'label' => 'Liabilities',
                'tone' => 'amber',
                'description' => 'Supplier balances and other obligations still owed.',
            ],
            'equity' => [
                'key' => 'equity',
                'label' => 'Equity',
                'tone' => 'violet',
                'description' => 'Owner capital and retained earnings accounts.',
            ],
            'revenue' => [
                'key' => 'revenue',
                'label' => 'Revenue',
                'tone' => 'emerald',
                'description' => 'Retail, wholesale, and stock adjustment gains.',
            ],
            'expenditure' => [
                'key' => 'expenditure',
                'label' => 'Expenditure',
                'tone' => 'rose',
                'description' => 'COGS, damages, expiries, and other stock losses.',
            ],
        ];
    }

    public static function accounts(): array
    {
        return [
            [
                'code' => '10100',
                'name' => 'Cash on Hand',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '10110',
                'name' => 'MTN Mobile Money',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '10120',
                'name' => 'Airtel Money',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '10130',
                'name' => 'Bank Account',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '10190',
                'name' => 'Other / Unspecified Receipts',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '11000',
                'name' => 'Accounts Receivable',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '11100',
                'name' => 'Insurance Claims Receivable',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '12100',
                'name' => 'Inventory - Drugs',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '17200',
                'name' => 'Leasehold Improvements',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '17300',
                'name' => 'Furniture & Fixtures',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '17400',
                'name' => 'Computers, IT Equipment',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '17500',
                'name' => 'Office Equipment',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '17900',
                'name' => 'Other Fixed Assets',
                'category' => 'assets',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '18200',
                'name' => 'Accumulated Depreciation - Leasehold Improvements',
                'category' => 'assets',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '18300',
                'name' => 'Accumulated Depreciation - Furniture & Fixtures',
                'category' => 'assets',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '18400',
                'name' => 'Accumulated Depreciation - Computers, IT Equipment',
                'category' => 'assets',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '18500',
                'name' => 'Accumulated Depreciation - Office Equipment',
                'category' => 'assets',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '18900',
                'name' => 'Accumulated Depreciation - Other Fixed Assets',
                'category' => 'assets',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '21000',
                'name' => 'Accounts Payable',
                'category' => 'liabilities',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '31000',
                'name' => 'Owner Equity',
                'category' => 'equity',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '32000',
                'name' => 'Retained Earnings',
                'category' => 'equity',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '41000',
                'name' => 'Retail Sales Revenue',
                'category' => 'revenue',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '42000',
                'name' => 'Wholesale Sales Revenue',
                'category' => 'revenue',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '43000',
                'name' => 'Inventory Adjustment Gain',
                'category' => 'revenue',
                'normal_balance' => 'credit',
            ],
            [
                'code' => '51000',
                'name' => 'Cost of Goods Sold',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '52000',
                'name' => 'Damaged Goods Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '52010',
                'name' => 'Expired Goods Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '52020',
                'name' => 'Stock Loss Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '52030',
                'name' => 'Inventory Variance Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '52040',
                'name' => 'Internal Use / Samples Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50100',
                'name' => 'Rent & Utilities Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50200',
                'name' => 'Salaries & Wages Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50300',
                'name' => 'Transport & Travel Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50400',
                'name' => 'Repairs & Maintenance Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50500',
                'name' => 'Bank Charges Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50600',
                'name' => 'Office & Admin Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50700',
                'name' => 'Other Operating Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50800',
                'name' => 'Depreciation Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '50900',
                'name' => 'Insurance Claim Write-Off Expense',
                'category' => 'expenditure',
                'normal_balance' => 'debit',
            ],
        ];
    }

    public static function groupedAccounts(): array
    {
        $grouped = [];

        foreach (self::accounts() as $account) {
            $grouped[$account['category']][] = $account;
        }

        return $grouped;
    }

    public static function account(string $code): array
    {
        foreach (self::accounts() as $account) {
            if ($account['code'] === $code) {
                return $account;
            }
        }

        return [
            'code' => $code,
            'name' => $code,
            'category' => 'assets',
            'normal_balance' => 'debit',
        ];
    }

    public static function accountCodeForPaymentMethod(?string $method): string
    {
        $bucket = PaymentMethodBuckets::normalize($method);

        return match ($bucket) {
            'cash' => '10100',
            'mtn' => '10110',
            'airtel' => '10120',
            'bank' => '10130',
            default => '10190',
        };
    }

    public static function salesRevenueCode(string $saleType): string
    {
        return strtolower($saleType) === 'wholesale' ? '42000' : '41000';
    }

    public static function openingBalanceEquityCode(): string
    {
        return '31000';
    }

    public static function stockAdjustmentAccountCode(string $direction, ?string $reason): string
    {
        if ($direction === 'increase') {
            return '43000';
        }

        return match ((string) $reason) {
            'damaged' => '52000',
            'expired' => '52010',
            'theft_loss' => '52020',
            'sample_use' => '52040',
            default => '52030',
        };
    }

    public static function formatBalance(float $amount, string $normalBalance): string
    {
        if (abs($amount) < 0.005) {
            return '0.00';
        }

        $suffix = $normalBalance === 'credit' ? 'Cr' : 'Dr';

        return number_format(abs($amount), 2) . ' ' . $suffix;
    }

    public static function statementAmount(array $account, float $balance): float
    {
        return match ($account['category']) {
            'assets', 'expenditure' => $account['normal_balance'] === 'debit' ? $balance : -$balance,
            default => $account['normal_balance'] === 'credit' ? $balance : -$balance,
        };
    }

    public static function trialBalanceColumns(float $balance, string $normalBalance): array
    {
        if (abs($balance) < 0.005) {
            return ['debit' => 0.0, 'credit' => 0.0];
        }

        if ($normalBalance === 'debit') {
            return $balance >= 0
                ? ['debit' => round($balance, 2), 'credit' => 0.0]
                : ['debit' => 0.0, 'credit' => round(abs($balance), 2)];
        }

        return $balance >= 0
            ? ['debit' => 0.0, 'credit' => round($balance, 2)]
            : ['debit' => round(abs($balance), 2), 'credit' => 0.0];
    }

    public static function manualExpenseAccounts(): array
    {
        $codes = ['50100', '50200', '50300', '50400', '50500', '50600', '50700'];

        return array_values(array_filter(
            self::accounts(),
            fn (array $account) => in_array($account['code'], $codes, true)
        ));
    }

    public static function fixedAssetDefinitions(): array
    {
        return [
            'leasehold_improvements' => [
                'key' => 'leasehold_improvements',
                'label' => 'Leasehold Improvements',
                'asset_account_code' => '17200',
                'accumulated_depreciation_account_code' => '18200',
            ],
            'furniture_fixtures' => [
                'key' => 'furniture_fixtures',
                'label' => 'Furniture & Fixtures',
                'asset_account_code' => '17300',
                'accumulated_depreciation_account_code' => '18300',
            ],
            'computers_it' => [
                'key' => 'computers_it',
                'label' => 'Computers, IT Equipment',
                'asset_account_code' => '17400',
                'accumulated_depreciation_account_code' => '18400',
            ],
            'office_equipment' => [
                'key' => 'office_equipment',
                'label' => 'Office Equipment',
                'asset_account_code' => '17500',
                'accumulated_depreciation_account_code' => '18500',
            ],
            'other_fixed_assets' => [
                'key' => 'other_fixed_assets',
                'label' => 'Other Fixed Assets',
                'asset_account_code' => '17900',
                'accumulated_depreciation_account_code' => '18900',
            ],
        ];
    }

    public static function defaultFixedAssetDefinition(): array
    {
        return self::fixedAssetDefinitions()['other_fixed_assets'];
    }

    public static function profitAndLossSections(): array
    {
        return [
            'sales_revenue' => [
                'key' => 'sales_revenue',
                'label' => 'Sales Revenue',
                'type' => 'revenue',
                'codes' => ['41000', '42000'],
            ],
            'other_income' => [
                'key' => 'other_income',
                'label' => 'Other Income',
                'type' => 'revenue',
                'codes' => ['43000'],
            ],
            'cost_of_sales' => [
                'key' => 'cost_of_sales',
                'label' => 'Cost Of Sales',
                'type' => 'expense',
                'codes' => ['51000'],
            ],
            'operating_expenses' => [
                'key' => 'operating_expenses',
                'label' => 'Operating Expenses',
                'type' => 'expense',
                'codes' => ['50100', '50200', '50300', '50400', '50500', '50600', '50700', '50900'],
            ],
            'depreciation' => [
                'key' => 'depreciation',
                'label' => 'Depreciation Expense',
                'type' => 'expense',
                'codes' => ['50800'],
            ],
            'stock_losses' => [
                'key' => 'stock_losses',
                'label' => 'Stock Losses & Write-Offs',
                'type' => 'expense',
                'codes' => ['52000', '52010', '52020', '52030', '52040'],
            ],
        ];
    }

    public static function balanceSheetSections(): array
    {
        return [
            'current_assets' => [
                'key' => 'current_assets',
                'label' => 'Current Assets',
                'side' => 'assets',
                'codes' => ['10100', '10110', '10120', '10130', '10190', '11000', '11100', '12100'],
            ],
            'fixed_assets' => [
                'key' => 'fixed_assets',
                'label' => 'Fixed Assets',
                'side' => 'assets',
                'codes' => ['17200', '17300', '17400', '17500', '17900', '18200', '18300', '18400', '18500', '18900'],
            ],
            'current_liabilities' => [
                'key' => 'current_liabilities',
                'label' => 'Current Liabilities',
                'side' => 'liabilities',
                'codes' => ['21000'],
            ],
            'equity' => [
                'key' => 'equity',
                'label' => 'Equity',
                'side' => 'equity',
                'codes' => ['31000', '32000'],
            ],
        ];
    }
}
