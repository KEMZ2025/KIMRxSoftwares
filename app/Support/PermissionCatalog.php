<?php

namespace App\Support;

class PermissionCatalog
{
    public static function definitions(): array
    {
        return [
            'dashboard.view' => [
                'module' => 'Dashboard',
                'action' => 'View Dashboard',
                'description' => 'Open the dashboard and branch overview.',
            ],
            'sales.view' => [
                'module' => 'Sales',
                'action' => 'View All Sales',
                'description' => 'Open the full sales list and sale details.',
            ],
            'sales.view_pending' => [
                'module' => 'Sales',
                'action' => 'View Pending Sales',
                'description' => 'Open the pending sales screen.',
            ],
            'sales.view_approved' => [
                'module' => 'Sales',
                'action' => 'View Approved Sales',
                'description' => 'Open the approved sales screen.',
            ],
            'sales.view_cancelled' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'View Cancelled Sales',
                'description' => 'Review cancelled sales and their audit trail.',
            ],
            'sales.create' => [
                'module' => 'Sales',
                'action' => 'Create Sales',
                'description' => 'Create new pending or approved sales.',
            ],
            'sales.proforma' => [
                'module' => 'Sales',
                'action' => 'Use Proforma',
                'description' => 'Create and manage proforma invoices.',
            ],
            'sales.edit' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Edit Pending Sales',
                'description' => 'Edit pending sales before approval.',
            ],
            'sales.edit_approved' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Edit Approved Sales',
                'description' => 'Edit approved sales with the protected stock rules.',
            ],
            'sales.approve' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Approve Sales',
                'description' => 'Approve pending sales into completed invoices.',
            ],
            'sales.cancel' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Cancel Sales',
                'description' => 'Cancel pending or approved sales with a reason.',
            ],
            'sales.restore' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Restore Cancelled Sales',
                'description' => 'Reverse a sale cancellation when stock and payments allow it.',
            ],
            'sales.discount' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Apply Sale Discounts',
                'description' => 'Create or change sale discounts inside the POS and sale edit screens.',
            ],
            'sales.price_override' => [
                'module' => 'Sensitive Sales Controls',
                'action' => 'Override Sale Price',
                'description' => 'Lower POS unit price below the official retail or wholesale selling price when authorized.',
            ],
            'products.view' => [
                'module' => 'Products',
                'action' => 'View Products',
                'description' => 'Open product lists and source history.',
            ],
            'products.create' => [
                'module' => 'Products',
                'action' => 'Create Products',
                'description' => 'Add new products to inventory.',
            ],
            'products.edit' => [
                'module' => 'Products',
                'action' => 'Edit Products',
                'description' => 'Edit product pricing, stock settings, and inventory fields.',
            ],
            'products.delete' => [
                'module' => 'Products',
                'action' => 'Delete Products',
                'description' => 'Deactivate or delete products where allowed.',
            ],
            'categories.view' => [
                'module' => 'Categories',
                'action' => 'View Categories',
                'description' => 'Open the categories screen.',
            ],
            'categories.manage' => [
                'module' => 'Categories',
                'action' => 'Manage Categories',
                'description' => 'Create, edit, and delete categories.',
            ],
            'units.view' => [
                'module' => 'Units',
                'action' => 'View Units',
                'description' => 'Open the units screen.',
            ],
            'units.manage' => [
                'module' => 'Units',
                'action' => 'Manage Units',
                'description' => 'Create, edit, and delete units.',
            ],
            'purchases.view' => [
                'module' => 'Purchases',
                'action' => 'View Purchases',
                'description' => 'Open purchase invoices and purchase history.',
            ],
            'purchases.create' => [
                'module' => 'Purchases',
                'action' => 'Create Purchases',
                'description' => 'Enter new purchase invoices.',
            ],
            'purchases.edit' => [
                'module' => 'Purchases',
                'action' => 'Edit Purchases',
                'description' => 'Edit purchase headers and safe purchase fields.',
            ],
            'purchases.receive' => [
                'module' => 'Purchases',
                'action' => 'Receive Purchases',
                'description' => 'Receive stock into purchase invoices.',
            ],
            'purchases.add_items' => [
                'module' => 'Purchases',
                'action' => 'Add Purchase Items',
                'description' => 'Add more items to existing purchase invoices.',
            ],
            'purchases.correct_items' => [
                'module' => 'Purchases',
                'action' => 'Correct Purchase Items',
                'description' => 'Correct mis-entered purchase items with audit history.',
            ],
            'customers.view' => [
                'module' => 'Customers',
                'action' => 'View Customers',
                'description' => 'Open customer records and statements.',
            ],
            'customers.create' => [
                'module' => 'Customers',
                'action' => 'Create Customers',
                'description' => 'Add new customer accounts.',
            ],
            'customers.edit' => [
                'module' => 'Customers',
                'action' => 'Edit Customers',
                'description' => 'Edit customer details and credit settings.',
            ],
            'customers.delete' => [
                'module' => 'Customers',
                'action' => 'Delete Customers',
                'description' => 'Deactivate or delete customer accounts where allowed.',
            ],
            'customers.receivables' => [
                'module' => 'Customers',
                'action' => 'View Receivables',
                'description' => 'Open unpaid customer invoices.',
            ],
            'customers.collections.view' => [
                'module' => 'Customers',
                'action' => 'View Collections',
                'description' => 'Open the customer collections ledger.',
            ],
            'customers.collections.collect' => [
                'module' => 'Customers',
                'action' => 'Collect Payments',
                'description' => 'Record invoice-specific customer collections.',
            ],
            'customers.collections.reverse' => [
                'module' => 'Customers',
                'action' => 'Reverse Collections',
                'description' => 'Reverse invoice-specific customer payments.',
            ],
            'suppliers.view' => [
                'module' => 'Suppliers',
                'action' => 'View Suppliers',
                'description' => 'Open suppliers, statements, and invoice history.',
            ],
            'suppliers.create' => [
                'module' => 'Suppliers',
                'action' => 'Create Suppliers',
                'description' => 'Add new suppliers.',
            ],
            'suppliers.edit' => [
                'module' => 'Suppliers',
                'action' => 'Edit Suppliers',
                'description' => 'Edit supplier details and deactivate suppliers.',
            ],
            'suppliers.delete' => [
                'module' => 'Suppliers',
                'action' => 'Delete Suppliers',
                'description' => 'Delete or deactivate suppliers where allowed.',
            ],
            'suppliers.payables' => [
                'module' => 'Suppliers',
                'action' => 'View Payables',
                'description' => 'Open unpaid supplier invoices.',
            ],
            'suppliers.payments.view' => [
                'module' => 'Suppliers',
                'action' => 'View Supplier Payments',
                'description' => 'Open the supplier payment ledger.',
            ],
            'suppliers.payments.pay' => [
                'module' => 'Suppliers',
                'action' => 'Pay Suppliers',
                'description' => 'Record invoice-specific supplier payments.',
            ],
            'stock.view' => [
                'module' => 'Stock',
                'action' => 'View Stock',
                'description' => 'Open the stock and batches screen.',
            ],
            'stock.adjust' => [
                'module' => 'Stock',
                'action' => 'Adjust Stock',
                'description' => 'Increase or decrease stock with audit reasons.',
            ],
            'cash_drawer.view' => [
                'module' => 'Cash Drawer',
                'action' => 'View Cash Drawer',
                'description' => 'Open the daily cash drawer summary, opening balance, and draw history.',
            ],
            'cash_drawer.manage' => [
                'module' => 'Cash Drawer',
                'action' => 'Manage Cash Drawer',
                'description' => 'Set the opening balance, manage shift and day closing, and record drawer cash draws with a reason.',
            ],
            'accounting.view' => [
                'module' => 'Accounting',
                'action' => 'View Accounting',
                'description' => 'Open the accounting hub and accountant summary screens.',
            ],
            'accounting.chart' => [
                'module' => 'Accounting',
                'action' => 'View Chart Of Accounts',
                'description' => 'Review the account list grouped by accounting category.',
            ],
            'accounting.general_ledger' => [
                'module' => 'Accounting',
                'action' => 'View General Ledger',
                'description' => 'Review transaction history with running balances.',
            ],
            'accounting.trial_balance' => [
                'module' => 'Accounting',
                'action' => 'View Trial Balance',
                'description' => 'Review debit and credit balances across all accounts as of a date.',
            ],
            'accounting.journals' => [
                'module' => 'Accounting',
                'action' => 'View Journals',
                'description' => 'Review balanced journal entries derived from pharmacy transactions.',
            ],
            'accounting.vouchers' => [
                'module' => 'Accounting',
                'action' => 'View Payment Vouchers',
                'description' => 'Review outgoing supplier disbursement vouchers.',
            ],
            'accounting.profit_loss' => [
                'module' => 'Accounting',
                'action' => 'View Profit & Loss',
                'description' => 'Review revenue, cost of sales, expenses, and net profit for a period.',
            ],
            'accounting.balance_sheet' => [
                'module' => 'Accounting',
                'action' => 'View Balance Sheet',
                'description' => 'Review assets, liabilities, equity, and current earnings as of a date.',
            ],
            'accounting.expenses.view' => [
                'module' => 'Accounting',
                'action' => 'View Expenses',
                'description' => 'Open posted manual expenses and expense history.',
            ],
            'accounting.expenses.manage' => [
                'module' => 'Accounting',
                'action' => 'Post Expenses',
                'description' => 'Post manual operating expenses into accounting.',
            ],
            'accounting.fixed_assets.view' => [
                'module' => 'Accounting',
                'action' => 'View Fixed Assets',
                'description' => 'Open fixed assets, depreciation, and net book values.',
            ],
            'accounting.fixed_assets.manage' => [
                'module' => 'Accounting',
                'action' => 'Manage Fixed Assets',
                'description' => 'Register fixed assets and their depreciation inputs.',
            ],
            'reports.view' => [
                'module' => 'Reports',
                'action' => 'View Reports',
                'description' => 'Open formal reports when the reports module is ready.',
            ],
            'insurance.view' => [
                'module' => 'Insurance',
                'action' => 'View Insurance Desk',
                'description' => 'Open insurers, claim summaries, insurance receivables, and remittance history.',
            ],
            'insurance.manage' => [
                'module' => 'Insurance',
                'action' => 'Manage Insurance Billing',
                'description' => 'Maintain insurers, update claim stages, and record or reverse insurer remittances.',
            ],
            'settings.view' => [
                'module' => 'Settings',
                'action' => 'View Settings',
                'description' => 'Open system settings when enabled.',
            ],
            'settings.manage' => [
                'module' => 'Settings',
                'action' => 'Manage Settings',
                'description' => 'Update company details, branch details, and print preferences.',
            ],
            'users.manage' => [
                'module' => 'Administration',
                'action' => 'Manage Users',
                'description' => 'Create users, assign branches, activate, and deactivate accounts.',
            ],
            'roles.manage' => [
                'module' => 'Administration',
                'action' => 'Manage Roles',
                'description' => 'Create and edit roles with screen permissions.',
            ],
            'audit.view' => [
                'module' => 'Administration',
                'action' => 'View Audit Trail',
                'description' => 'Review the central audit trail for sensitive changes across operations, stock, cash drawer, settings, and client access.',
            ],
            'data_import.manage' => [
                'module' => 'Administration',
                'action' => 'Manage Data Import',
                'description' => 'Download migration templates, preview CSV data, and import medicines, customers, suppliers, and opening stock for a client.',
            ],
        ];
    }

    public static function groupedDefinitions(): array
    {
        $grouped = [];

        foreach (self::definitions() as $key => $definition) {
            $grouped[$definition['module']][$key] = $definition;
        }

        return $grouped;
    }

    public static function defaultRoles(): array
    {
        $allPermissions = array_keys(self::definitions());

        return [
            'admin' => [
                'name' => 'Admin',
                'description' => 'Full control over operations, accounting actions, and user administration.',
                'permissions' => $allPermissions,
                'is_system_role' => true,
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Handles receivables, payables, and financial review screens.',
                'permissions' => [
                    'dashboard.view',
                    'sales.view',
                    'sales.view_approved',
                    'sales.view_cancelled',
                    'purchases.view',
                    'customers.view',
                    'customers.receivables',
                    'customers.collections.view',
                    'customers.collections.collect',
                    'customers.collections.reverse',
                    'suppliers.view',
                    'suppliers.payables',
                    'suppliers.payments.view',
                    'suppliers.payments.pay',
                    'accounting.view',
                    'accounting.chart',
                    'accounting.general_ledger',
                    'accounting.trial_balance',
                    'accounting.journals',
                    'accounting.vouchers',
                    'accounting.profit_loss',
                    'accounting.balance_sheet',
                    'accounting.expenses.view',
                    'accounting.expenses.manage',
                    'accounting.fixed_assets.view',
                    'accounting.fixed_assets.manage',
                    'reports.view',
                    'insurance.view',
                    'insurance.manage',
                ],
                'is_system_role' => true,
            ],
            'dispenser' => [
                'name' => 'Dispenser',
                'description' => 'Handles day-to-day dispensing and customer-facing sale entry.',
                'permissions' => [
                    'dashboard.view',
                    'sales.view',
                    'sales.view_pending',
                    'sales.view_approved',
                    'sales.create',
                    'sales.proforma',
                    'customers.view',
                    'customers.create',
                    'insurance.view',
                    'products.view',
                ],
                'is_system_role' => true,
            ],
            'stock-manager' => [
                'name' => 'Stock Manager',
                'description' => 'Handles products, purchases, suppliers, and stock adjustments.',
                'permissions' => [
                    'dashboard.view',
                    'products.view',
                    'products.create',
                    'products.edit',
                    'categories.view',
                    'categories.manage',
                    'units.view',
                    'units.manage',
                    'purchases.view',
                    'purchases.create',
                    'purchases.edit',
                    'purchases.receive',
                    'purchases.add_items',
                    'purchases.correct_items',
                    'suppliers.view',
                    'suppliers.create',
                    'suppliers.edit',
                    'suppliers.payables',
                    'stock.view',
                    'stock.adjust',
                ],
                'is_system_role' => true,
            ],
            'cashier' => [
                'name' => 'Cashier',
                'description' => 'Handles invoice creation and customer collections without deeper stock edits.',
                'permissions' => [
                    'dashboard.view',
                    'sales.view',
                    'sales.view_pending',
                    'sales.view_approved',
                    'sales.create',
                    'sales.proforma',
                    'customers.view',
                    'customers.receivables',
                    'customers.collections.view',
                    'customers.collections.collect',
                    'insurance.view',
                    'cash_drawer.view',
                    'cash_drawer.manage',
                    'products.view',
                ],
                'is_system_role' => true,
            ],
        ];
    }
}
