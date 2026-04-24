<?php

namespace App\Support;

use App\Models\ClientSetting;

class ClientFeatureAccess
{
    public static function defaultSettingValues(): array
    {
        return [
            'retail_pos_enabled' => true,
            'wholesale_pos_enabled' => true,
            'purchases_enabled' => true,
            'suppliers_enabled' => true,
            'customers_enabled' => true,
            'inventory_enabled' => true,
            'expiry_alerts_enabled' => true,
            'cash_drawer_enabled' => false,
            'accounts_enabled' => true,
            'reports_enabled' => true,
            'insurance_enabled' => false,
            'efris_enabled' => false,
            'employees_enabled' => true,
            'proforma_enabled' => true,
            'dispensing_price_guide_enabled' => false,
            'support_enabled' => true,
            'accounting_chart_enabled' => true,
            'accounting_general_ledger_enabled' => true,
            'accounting_trial_balance_enabled' => true,
            'accounting_journals_enabled' => true,
            'accounting_vouchers_enabled' => true,
            'accounting_profit_loss_enabled' => true,
            'accounting_balance_sheet_enabled' => true,
            'accounting_expenses_enabled' => true,
            'accounting_fixed_assets_enabled' => true,
        ];
    }

    public static function moduleDefinitions(): array
    {
        return [
            [
                'field' => 'retail_pos_enabled',
                'label' => 'Retail Sales / POS',
                'description' => 'Allow retail sales screens, invoices, and cashier workflows.',
            ],
            [
                'field' => 'wholesale_pos_enabled',
                'label' => 'Wholesale Sales / POS',
                'description' => 'Allow wholesale sales workflows for branches using wholesale mode.',
            ],
            [
                'field' => 'proforma_enabled',
                'label' => 'Proforma',
                'description' => 'Allow proforma quotation and draft invoice flows.',
            ],
            [
                'field' => 'dispensing_price_guide_enabled',
                'label' => 'Dispensing Price Guide',
                'description' => 'Show admin-defined quantity and quick-quote price guides in the POS without changing stock or totals automatically.',
            ],
            [
                'field' => 'purchases_enabled',
                'label' => 'Purchases',
                'description' => 'Allow purchase invoices, receiving, and purchase corrections.',
            ],
            [
                'field' => 'suppliers_enabled',
                'label' => 'Suppliers',
                'description' => 'Allow supplier records, payables, and supplier payments.',
            ],
            [
                'field' => 'customers_enabled',
                'label' => 'Customers',
                'description' => 'Allow customer records, receivables, and collections.',
            ],
            [
                'field' => 'inventory_enabled',
                'label' => 'Inventory',
                'description' => 'Allow products, categories, units, stock batches, and stock adjustments.',
            ],
            [
                'field' => 'expiry_alerts_enabled',
                'label' => 'Expiry Alert Reminders',
                'description' => 'Show soon-expiry reminders for dispensers and admins at 8am, 1pm, and 6pm, including the live in-page warning on working screens.',
            ],
            [
                'field' => 'cash_drawer_enabled',
                'label' => 'Cash Drawer Control',
                'description' => 'Track daily drawer cash, run shift and end-of-day closing, and warn cashiers and admins when the configured drawer threshold is reached.',
            ],
            [
                'field' => 'accounts_enabled',
                'label' => 'Accounting Module',
                'description' => 'Allow the accounting workspace and the enabled accounting modules for this client.',
            ],
            [
                'field' => 'reports_enabled',
                'label' => 'Reports',
                'description' => 'Allow the performance and operational reports workspace.',
            ],
            [
                'field' => 'insurance_enabled',
                'label' => 'Insurance Claims & Billing',
                'description' => 'Allow insurer setup, insurance sale billing, split patient top-up handling, insurer remittance tracking, and claim status monitoring.',
            ],
            [
                'field' => 'efris_enabled',
                'label' => 'URA / EFRIS Integration',
                'description' => 'Prepare approved sales for URA EFRIS submission and future fiscal compliance syncing for this client.',
            ],
        ];
    }

    public static function accountingFeatureDefinitions(): array
    {
        return [
            [
                'field' => 'accounting_chart_enabled',
                'label' => 'Chart Of Accounts',
                'description' => 'View grouped accounts and balances.',
            ],
            [
                'field' => 'accounting_general_ledger_enabled',
                'label' => 'General Ledger',
                'description' => 'Review account movements and running balances.',
            ],
            [
                'field' => 'accounting_trial_balance_enabled',
                'label' => 'Trial Balance',
                'description' => 'Review debit and credit balances as of a date.',
            ],
            [
                'field' => 'accounting_journals_enabled',
                'label' => 'Journals',
                'description' => 'Review journalized pharmacy transactions.',
            ],
            [
                'field' => 'accounting_vouchers_enabled',
                'label' => 'Payment Vouchers',
                'description' => 'Review outgoing supplier payment vouchers.',
            ],
            [
                'field' => 'accounting_profit_loss_enabled',
                'label' => 'Profit & Loss',
                'description' => 'Review revenue, costs, expenses, and net performance.',
            ],
            [
                'field' => 'accounting_balance_sheet_enabled',
                'label' => 'Balance Sheet',
                'description' => 'Review assets, liabilities, and equity positions.',
            ],
            [
                'field' => 'accounting_expenses_enabled',
                'label' => 'Expenses',
                'description' => 'View and post manual operating expenses.',
            ],
            [
                'field' => 'accounting_fixed_assets_enabled',
                'label' => 'Fixed Assets',
                'description' => 'View and register fixed assets plus depreciation.',
            ],
        ];
    }

    public static function settingFields(): array
    {
        return array_keys(self::defaultSettingValues());
    }

    public static function valuesFromSettings(?ClientSetting $settings = null): array
    {
        $values = self::defaultSettingValues();

        if (!$settings) {
            return $values;
        }

        foreach (self::settingFields() as $field) {
            if (array_key_exists($field, $settings->getAttributes())) {
                $values[$field] = (bool) $settings->getAttribute($field);
            }
        }

        return $values;
    }

    public static function permissionEnabled(?ClientSetting $settings, string $permissionKey): bool
    {
        if (!$settings || $permissionKey === '') {
            return true;
        }

        return match (true) {
            str_starts_with($permissionKey, 'reports.') => (bool) $settings->reports_enabled,
            str_starts_with($permissionKey, 'insurance.') => self::insuranceEnabled($settings),
            str_starts_with($permissionKey, 'accounting.') => self::accountingPermissionEnabled($settings, $permissionKey),
            str_starts_with($permissionKey, 'cash_drawer.') => self::cashDrawerEnabled($settings),
            str_starts_with($permissionKey, 'purchases.') => (bool) $settings->purchases_enabled,
            str_starts_with($permissionKey, 'suppliers.') => (bool) $settings->suppliers_enabled,
            str_starts_with($permissionKey, 'customers.') => (bool) $settings->customers_enabled,
            str_starts_with($permissionKey, 'stock.'),
            str_starts_with($permissionKey, 'products.'),
            str_starts_with($permissionKey, 'categories.'),
            str_starts_with($permissionKey, 'units.') => (bool) $settings->inventory_enabled,
            $permissionKey === 'sales.proforma' => self::salesEnabled($settings) && (bool) $settings->proforma_enabled,
            str_starts_with($permissionKey, 'sales.') => self::salesEnabled($settings),
            default => true,
        };
    }

    public static function salesEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return true;
        }

        return (bool) $settings->retail_pos_enabled || (bool) $settings->wholesale_pos_enabled;
    }

    public static function dispensingPriceGuideEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return true;
        }

        return self::salesEnabled($settings) && (bool) $settings->dispensing_price_guide_enabled;
    }

    public static function cashDrawerEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return false;
        }

        return self::salesEnabled($settings) && (bool) $settings->cash_drawer_enabled;
    }

    public static function efrisEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return false;
        }

        return self::salesEnabled($settings) && (bool) $settings->efris_enabled;
    }

    public static function insuranceEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return false;
        }

        return self::salesEnabled($settings) && (bool) $settings->insurance_enabled;
    }

    public static function expiryAlertsEnabled(?ClientSetting $settings): bool
    {
        if (!$settings) {
            return true;
        }

        return (bool) $settings->inventory_enabled && (bool) $settings->expiry_alerts_enabled;
    }

    private static function accountingPermissionEnabled(ClientSetting $settings, string $permissionKey): bool
    {
        if (!(bool) $settings->accounts_enabled) {
            return false;
        }

        return match ($permissionKey) {
            'accounting.chart' => (bool) $settings->accounting_chart_enabled,
            'accounting.general_ledger' => (bool) $settings->accounting_general_ledger_enabled,
            'accounting.trial_balance' => (bool) $settings->accounting_trial_balance_enabled,
            'accounting.journals' => (bool) $settings->accounting_journals_enabled,
            'accounting.vouchers' => (bool) $settings->accounting_vouchers_enabled,
            'accounting.profit_loss' => (bool) $settings->accounting_profit_loss_enabled,
            'accounting.balance_sheet' => (bool) $settings->accounting_balance_sheet_enabled,
            'accounting.expenses.view',
            'accounting.expenses.manage' => (bool) $settings->accounting_expenses_enabled,
            'accounting.fixed_assets.view',
            'accounting.fixed_assets.manage' => (bool) $settings->accounting_fixed_assets_enabled,
            default => true,
        };
    }
}
