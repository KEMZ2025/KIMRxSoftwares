<?php

namespace App\Support;

class ClientPackagePresetCatalog
{
    public const PRESET_ESSENTIAL = 'essential';
    public const PRESET_PROFESSIONAL = 'professional';
    public const PRESET_PREMIUM = 'premium';
    public const PRESET_ENTERPRISE = 'enterprise';

    public static function definitions(): array
    {
        return [
            self::PRESET_ESSENTIAL => [
                'label' => 'Essential',
                'description' => 'Core daily pharmacy operations for a small branch.',
                'active_user_limit' => 2,
                'feature_values' => self::featureValuesFor([
                    'retail_pos_enabled' => true,
                    'wholesale_pos_enabled' => false,
                    'purchases_enabled' => true,
                    'suppliers_enabled' => true,
                    'customers_enabled' => true,
                    'inventory_enabled' => true,
                    'expiry_alerts_enabled' => false,
                    'cash_drawer_enabled' => false,
                    'accounts_enabled' => false,
                    'reports_enabled' => false,
                    'insurance_enabled' => false,
                    'efris_enabled' => false,
                    'employees_enabled' => true,
                    'proforma_enabled' => true,
                    'dispensing_price_guide_enabled' => false,
                    'support_enabled' => true,
                    'accounting_chart_enabled' => false,
                    'accounting_general_ledger_enabled' => false,
                    'accounting_trial_balance_enabled' => false,
                    'accounting_journals_enabled' => false,
                    'accounting_vouchers_enabled' => false,
                    'accounting_profit_loss_enabled' => false,
                    'accounting_balance_sheet_enabled' => false,
                    'accounting_expenses_enabled' => false,
                    'accounting_fixed_assets_enabled' => false,
                ]),
            ],
            self::PRESET_PROFESSIONAL => [
                'label' => 'Professional',
                'description' => 'Operations plus reporting and stronger day-to-day controls.',
                'active_user_limit' => 5,
                'feature_values' => self::featureValuesFor([
                    'retail_pos_enabled' => true,
                    'wholesale_pos_enabled' => true,
                    'purchases_enabled' => true,
                    'suppliers_enabled' => true,
                    'customers_enabled' => true,
                    'inventory_enabled' => true,
                    'expiry_alerts_enabled' => true,
                    'cash_drawer_enabled' => true,
                    'accounts_enabled' => false,
                    'reports_enabled' => true,
                    'insurance_enabled' => false,
                    'efris_enabled' => false,
                    'employees_enabled' => true,
                    'proforma_enabled' => true,
                    'dispensing_price_guide_enabled' => true,
                    'support_enabled' => true,
                    'accounting_chart_enabled' => false,
                    'accounting_general_ledger_enabled' => false,
                    'accounting_trial_balance_enabled' => false,
                    'accounting_journals_enabled' => false,
                    'accounting_vouchers_enabled' => false,
                    'accounting_profit_loss_enabled' => false,
                    'accounting_balance_sheet_enabled' => false,
                    'accounting_expenses_enabled' => false,
                    'accounting_fixed_assets_enabled' => false,
                ]),
            ],
            self::PRESET_PREMIUM => [
                'label' => 'Premium',
                'description' => 'Full pharmacy controls with accounting access included.',
                'active_user_limit' => 10,
                'feature_values' => self::featureValuesFor([
                    'retail_pos_enabled' => true,
                    'wholesale_pos_enabled' => true,
                    'purchases_enabled' => true,
                    'suppliers_enabled' => true,
                    'customers_enabled' => true,
                    'inventory_enabled' => true,
                    'expiry_alerts_enabled' => true,
                    'cash_drawer_enabled' => true,
                    'accounts_enabled' => true,
                    'reports_enabled' => true,
                    'insurance_enabled' => false,
                    'efris_enabled' => false,
                    'employees_enabled' => true,
                    'proforma_enabled' => true,
                    'dispensing_price_guide_enabled' => true,
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
                ]),
            ],
            self::PRESET_ENTERPRISE => [
                'label' => 'Enterprise',
                'description' => 'Broad access for complex pharmacies, groups, and custom rollouts.',
                'active_user_limit' => null,
                'feature_values' => self::featureValuesFor([
                    'retail_pos_enabled' => true,
                    'wholesale_pos_enabled' => true,
                    'purchases_enabled' => true,
                    'suppliers_enabled' => true,
                    'customers_enabled' => true,
                    'inventory_enabled' => true,
                    'expiry_alerts_enabled' => true,
                    'cash_drawer_enabled' => true,
                    'accounts_enabled' => true,
                    'reports_enabled' => true,
                    'insurance_enabled' => true,
                    'efris_enabled' => true,
                    'employees_enabled' => true,
                    'proforma_enabled' => true,
                    'dispensing_price_guide_enabled' => true,
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
                ]),
            ],
        ];
    }

    public static function options(): array
    {
        return collect(self::definitions())
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['label']])
            ->all();
    }

    public static function exists(?string $preset): bool
    {
        return is_string($preset) && array_key_exists($preset, self::definitions());
    }

    public static function preset(?string $preset): ?array
    {
        return self::definitions()[$preset] ?? null;
    }

    public static function label(?string $preset): string
    {
        return self::preset($preset)['label'] ?? 'Custom';
    }

    private static function featureValuesFor(array $overrides): array
    {
        return array_replace(ClientFeatureAccess::defaultSettingValues(), $overrides);
    }
}
