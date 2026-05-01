@php
    $authUser = auth()->user();
    $isSuperAdmin = $authUser?->isSuperAdmin() ?? false;
    $tenantWorkspaceActive = !$isSuperAdmin || ($authUser?->hasSelectedActingContext() ?? false);
    $displayClientName = $clientName ?? ($tenantWorkspaceActive
        ? (optional($authUser?->client)->name ?? 'N/A')
        : 'Owner Workspace');
    $displayBranchName = $branchName ?? ($tenantWorkspaceActive
        ? (optional($authUser?->branch)->name ?? 'N/A')
        : 'Choose client context');
    $currentYear = now(config('app.timezone', 'Africa/Nairobi'))->year;
    $appVersion = config('app.version', 'v1.0.0');

    $canViewDashboard = $tenantWorkspaceActive && ($authUser?->hasPermission('dashboard.view') ?? false);

    $canViewSales = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
        'sales.view',
        'sales.view_pending',
        'sales.view_approved',
        'sales.view_cancelled',
        'sales.create',
        'sales.proforma',
        'sales.edit',
        'sales.edit_approved',
        'sales.approve',
        'sales.cancel',
        'sales.restore',
    ]) ?? false);
    $canCreateSales = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.create') ?? false);
    $canUseProforma = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.proforma') ?? false);
    $canViewPendingSales = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.view_pending') ?? false);
    $canViewApprovedSales = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.view_approved') ?? false);
    $canViewCancelledSales = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.view_cancelled') ?? false);
    $canViewAllSales = $tenantWorkspaceActive && ($authUser?->hasPermission('sales.view') ?? false);
    $canViewCashDrawer = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['cash_drawer.view', 'cash_drawer.manage']) ?? false);

    $canViewProducts = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
        'products.view',
        'products.create',
        'products.edit',
        'products.delete',
        'categories.view',
        'categories.manage',
        'units.view',
        'units.manage',
    ]) ?? false);
    $canViewProductList = $tenantWorkspaceActive && ($authUser?->hasPermission('products.view') ?? false);
    $canCreateProducts = $tenantWorkspaceActive && ($authUser?->hasPermission('products.create') ?? false);
    $canViewCategories = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['categories.view', 'categories.manage']) ?? false);
    $canViewUnits = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['units.view', 'units.manage']) ?? false);

    $canViewPurchases = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
        'purchases.view',
        'purchases.create',
        'purchases.edit',
        'purchases.receive',
        'purchases.add_items',
        'purchases.correct_items',
    ]) ?? false);
    $canViewPurchaseList = $tenantWorkspaceActive && ($authUser?->hasPermission('purchases.view') ?? false);
    $canCreatePurchases = $tenantWorkspaceActive && ($authUser?->hasPermission('purchases.create') ?? false);

    $canViewCustomers = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
        'customers.view',
        'customers.create',
        'customers.receivables',
        'customers.collections.view',
        'customers.collections.collect',
        'customers.collections.reverse',
    ]) ?? false);
    $canCustomerList = $tenantWorkspaceActive && ($authUser?->hasPermission('customers.view') ?? false);
    $canCustomerCreate = $tenantWorkspaceActive && ($authUser?->hasPermission('customers.create') ?? false);
    $canCustomerReceivables = $tenantWorkspaceActive && ($authUser?->hasPermission('customers.receivables') ?? false);
    $canCustomerCollections = $tenantWorkspaceActive && ($authUser?->hasPermission('customers.collections.view') ?? false);
    $canViewInsurance = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['insurance.view', 'insurance.manage']) ?? false);
    $canInsuranceClaims = $tenantWorkspaceActive && ($authUser?->hasPermission('insurance.view') ?? false);
    $canInsuranceManage = $tenantWorkspaceActive && ($authUser?->hasPermission('insurance.manage') ?? false);

    $canViewSuppliers = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
        'suppliers.view',
        'suppliers.create',
        'suppliers.edit',
        'suppliers.payables',
        'suppliers.payments.view',
        'suppliers.payments.pay',
    ]) ?? false);
    $canSupplierList = $tenantWorkspaceActive && ($authUser?->hasPermission('suppliers.view') ?? false);
    $canSupplierCreate = $tenantWorkspaceActive && ($authUser?->hasPermission('suppliers.create') ?? false);
    $canSupplierPayables = $tenantWorkspaceActive && ($authUser?->hasPermission('suppliers.payables') ?? false);
    $canSupplierPayments = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['suppliers.payments.view', 'suppliers.payments.pay']) ?? false);

    $canViewStock = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['stock.view', 'stock.adjust']) ?? false);
    $canViewAccounting = $tenantWorkspaceActive && ($authUser?->hasAnyPermission([
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
    ]) ?? false);
    $canAccountingHome = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.view') ?? false);
    $canAccountingChart = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.chart') ?? false);
    $canAccountingLedger = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.general_ledger') ?? false);
    $canAccountingTrialBalance = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.trial_balance') ?? false);
    $canAccountingJournals = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.journals') ?? false);
    $canAccountingVouchers = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.vouchers') ?? false);
    $canAccountingProfitLoss = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.profit_loss') ?? false);
    $canAccountingBalanceSheet = $tenantWorkspaceActive && ($authUser?->hasPermission('accounting.balance_sheet') ?? false);
    $canAccountingExpenses = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['accounting.expenses.view', 'accounting.expenses.manage']) ?? false);
    $canAccountingFixedAssets = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['accounting.fixed_assets.view', 'accounting.fixed_assets.manage']) ?? false);

    $canViewReports = $tenantWorkspaceActive && ($authUser?->hasPermission('reports.view') ?? false);
    $canViewSettings = $tenantWorkspaceActive && ($authUser?->hasAnyPermission(['settings.view', 'settings.manage']) ?? false);
    $canManageUsers = $tenantWorkspaceActive && ($authUser?->hasPermission('users.manage') ?? false);
    $canManageRoles = $tenantWorkspaceActive && ($authUser?->hasPermission('roles.manage') ?? false);
    $canManageDataImport = $tenantWorkspaceActive && ($authUser?->hasPermission('data_import.manage') ?? false);
    $canViewAudit = (
        $isSuperAdmin
        || ($tenantWorkspaceActive && ($authUser?->hasPermission('audit.view') ?? false))
    ) && \Illuminate\Support\Facades\Route::has('admin.audit.index');
    $canViewPlatform = $isSuperAdmin && \Illuminate\Support\Facades\Route::has('admin.platform.index');
    $canManagePlatformClients = $isSuperAdmin && \Illuminate\Support\Facades\Route::has('admin.platform.clients.index');
    $canViewAdmin = $canManageUsers || $canManageRoles || $canManageDataImport || $canViewSettings || $canViewAudit || $canViewPlatform || $canManagePlatformClients;
    $expiryWarning = session('expiry_warning');
    $cashDrawerWarning = session('cash_drawer_warning');
    $expiryAlertFeatureEnabled = $tenantWorkspaceActive
        && $authUser
        && \Illuminate\Support\Facades\Route::has('alerts.expiry-reminder')
        && \App\Support\InventoryExpiryAlerts::shouldWarnUser($authUser);
    $expiryAlertConfig = [
        'enabled' => $expiryAlertFeatureEnabled,
        'endpoint' => $expiryAlertFeatureEnabled ? route('alerts.expiry-reminder') : null,
        'hours' => \App\Support\InventoryExpiryAlerts::reminderHours(),
        'initialWarning' => $expiryAlertFeatureEnabled && is_array($expiryWarning) && (int) ($expiryWarning['count'] ?? 0) > 0
            ? $expiryWarning
            : null,
    ];
    $cashDrawerAlertFeatureEnabled = $tenantWorkspaceActive
        && $authUser
        && \Illuminate\Support\Facades\Route::has('alerts.cash-drawer')
        && \App\Support\CashDrawerAlerts::shouldWarnUser($authUser);
    $cashDrawerAlertConfig = [
        'enabled' => $cashDrawerAlertFeatureEnabled,
        'endpoint' => $cashDrawerAlertFeatureEnabled ? route('alerts.cash-drawer') : null,
        'pollSeconds' => \App\Support\CashDrawerAlerts::pollSeconds(),
        'initialWarning' => $cashDrawerAlertFeatureEnabled && is_array($cashDrawerWarning) && (int) ($cashDrawerWarning['count'] ?? 0) > 0
            ? $cashDrawerWarning
            : null,
    ];

    $salesOpen = $canViewSales && request()->routeIs('sales.*');
    $cashDrawerOpen = $canViewCashDrawer && request()->routeIs('cash-drawer.*');
    $productsOpen = $canViewProducts && (request()->routeIs('products.*') || request()->routeIs('categories.*') || request()->routeIs('units.*'));
    $purchasesOpen = $canViewPurchases && request()->routeIs('purchases.*');
    $customersOpen = $canViewCustomers && request()->routeIs('customers.*');
    $insuranceOpen = $canViewInsurance && request()->routeIs('insurance.*');
    $suppliersOpen = $canViewSuppliers && request()->routeIs('suppliers.*');
    $stockOpen = $canViewStock && request()->routeIs('stock.*');
    $accountingOpen = $canViewAccounting && request()->routeIs('accounting.*');
    $adminOpen = $canViewAdmin && request()->routeIs('admin.*');

    $stockEnabled = $canViewStock && \Illuminate\Support\Facades\Route::has('stock.index');
    $cashDrawerEnabled = $canViewCashDrawer && \Illuminate\Support\Facades\Route::has('cash-drawer.index');
    $reportsEnabled = $canViewReports && \Illuminate\Support\Facades\Route::has('reports.index');
    $supportEnabled = $tenantWorkspaceActive && \Illuminate\Support\Facades\Route::has('support.index');
    $settingsEnabled = $canViewSettings && \Illuminate\Support\Facades\Route::has('settings.index');
@endphp

<aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="brand">
            <div class="brand-copy">
                <h2>{{ $displayClientName }}</h2>
                <p>{{ $displayBranchName }}</p>
            </div>
            </div>

            <button
            type="button"
            class="sidebar-toggle sidebar-toggle-inline"
            id="sidebarCollapseButton"
            onclick="toggleSidebar(true)"
            aria-label="Collapse sidebar"
            title="Collapse sidebar"
        >
            <span aria-hidden="true">&lsaquo;</span>
        </button>
    </div>

    @if ($isSuperAdmin)
        <div class="owner-badge">{{ $tenantWorkspaceActive ? 'Platform Owner' : 'Owner Workspace' }}</div>
        <div class="menu-hint">
            {{ $tenantWorkspaceActive ? ('Context: ' . $displayBranchName) : 'Choose a client and branch from Owner Workspace to open tenant modules.' }}
        </div>
    @endif

    @if (is_array($expiryWarning) && (int) ($expiryWarning['count'] ?? 0) > 0)
        <div class="sidebar-alert sidebar-alert-warning">
            <div class="sidebar-alert-title">Expiry Warning</div>
            <div class="sidebar-alert-body">
                {{ number_format((int) $expiryWarning['count']) }} batch{{ (int) $expiryWarning['count'] === 1 ? '' : 'es' }} need attention. Dispense the nearest-expiry stock first.
            </div>

            @if (!empty($expiryWarning['items']))
                <div class="sidebar-alert-list">
                    @foreach ($expiryWarning['items'] as $item)
                        <div class="sidebar-alert-item">
                            <strong>{{ $item['product_name'] }}</strong>
                            <span>
                                {{ $item['batch_number'] }}
                                @if (!empty($item['strength']))
                                    | {{ $item['strength'] }}
                                @endif
                                | {{ $item['risk_label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if (is_array($cashDrawerWarning) && (int) ($cashDrawerWarning['count'] ?? 0) > 0)
        <div class="sidebar-alert sidebar-alert-cash">
            <div class="sidebar-alert-title">Cash Drawer Alert</div>
            <div class="sidebar-alert-body">
                Drawer balance is {{ ($cashDrawerWarning['currency_symbol'] ?? 'UGX') . ' ' . number_format((float) ($cashDrawerWarning['current_balance'] ?? 0), 2) }}.
                Threshold: {{ ($cashDrawerWarning['currency_symbol'] ?? 'UGX') . ' ' . number_format((float) ($cashDrawerWarning['alert_threshold'] ?? 0), 2) }}.
                Record a draw with a reason.
            </div>
            <div class="sidebar-alert-list">
                <div class="sidebar-alert-item">
                    <strong>Cash Sales</strong>
                    <span>{{ ($cashDrawerWarning['currency_symbol'] ?? 'UGX') . ' ' . number_format((float) ($cashDrawerWarning['cash_sales_total'] ?? 0), 2) }}</span>
                </div>
                <div class="sidebar-alert-item">
                    <strong>Cash Collections</strong>
                    <span>{{ ($cashDrawerWarning['currency_symbol'] ?? 'UGX') . ' ' . number_format((float) ($cashDrawerWarning['cash_collections_total'] ?? 0), 2) }}</span>
                </div>
            </div>
        </div>
    @endif

    <nav class="menu" aria-label="Main navigation">
        @if ($canViewDashboard)
            <a href="{{ route('dashboard') }}" class="menu-link {{ request()->routeIs('dashboard') ? 'active-link' : '' }}" data-tooltip="Dashboard" aria-label="Dashboard">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 10.5 12 3l9 7.5"></path>
                        <path d="M5 9.5V20h14V9.5"></path>
                        <path d="M10 20v-6h4v6"></path>
                    </svg>
                </span>
                <span class="menu-label">Dashboard</span>
            </a>
        @endif

        @if ($canViewSales)
            <details class="menu-group" {{ $salesOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $salesOpen ? 'active-link' : '' }}" data-tooltip="Sales" aria-label="Sales">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 4h10v16l-2-1.5L13 20l-2-1.5L9 20l-2-1.5L5 20V6a2 2 0 0 1 2-2Z"></path>
                            <path d="M9 8h6"></path>
                            <path d="M9 12h6"></path>
                            <path d="M9 16h4"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Sales</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canCreateSales)
                        <a href="{{ route('sales.create') }}" class="{{ request()->routeIs('sales.create') ? 'active-sublink' : '' }}">New Sale</a>
                    @endif
                    @if ($canUseProforma)
                        <a href="{{ route('sales.proforma.create') }}" class="{{ request()->routeIs('sales.proforma.create') || request()->routeIs('sales.editProforma') || request()->routeIs('sales.proforma') ? 'active-sublink' : '' }}">Proforma</a>
                    @endif
                    @if ($canViewPendingSales)
                        <a href="{{ route('sales.pending') }}" class="{{ request()->routeIs('sales.pending') ? 'active-sublink' : '' }}">Pending</a>
                    @endif
                    @if ($canViewApprovedSales)
                        <a href="{{ route('sales.approved') }}" class="{{ request()->routeIs('sales.approved') ? 'active-sublink' : '' }}">Approved</a>
                    @endif
                    @if ($canViewCancelledSales)
                        <a href="{{ route('sales.cancelled') }}" class="{{ request()->routeIs('sales.cancelled') ? 'active-sublink' : '' }}">Cancelled</a>
                    @endif
                    @if ($canViewAllSales)
                        <a href="{{ route('sales.index') }}" class="{{ request()->routeIs('sales.index') ? 'active-sublink' : '' }}">All Sales</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($cashDrawerEnabled)
            <a href="{{ route('cash-drawer.index') }}" class="menu-link {{ $cashDrawerOpen ? 'active-link' : '' }}" data-tooltip="Cash Drawer" aria-label="Cash Drawer">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4 7h16v10H4z"></path>
                        <path d="M4 10h16"></path>
                        <path d="M8 14h3"></path>
                    </svg>
                </span>
                <span class="menu-label">Cash Drawer</span>
            </a>
        @endif

        @if ($canViewProducts)
            <details class="menu-group" {{ $productsOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $productsOpen ? 'active-link' : '' }}" data-tooltip="Products" aria-label="Products">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 3 4 7l8 4 8-4-8-4Z"></path>
                            <path d="M4 7v10l8 4 8-4V7"></path>
                            <path d="M12 11v10"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Products</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canViewProductList)
                        <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.index') ? 'active-sublink' : '' }}">Products List</a>
                    @endif
                    @if ($canCreateProducts)
                        <a href="{{ route('products.create') }}" class="{{ request()->routeIs('products.create') ? 'active-sublink' : '' }}">Add Product</a>
                    @endif
                    @if ($canViewCategories)
                        <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active-sublink' : '' }}">Categories</a>
                    @endif
                    @if ($canViewUnits)
                        <a href="{{ route('units.index') }}" class="{{ request()->routeIs('units.*') ? 'active-sublink' : '' }}">Units</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($canViewPurchases)
            <details class="menu-group" {{ $purchasesOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $purchasesOpen ? 'active-link' : '' }}" data-tooltip="Purchases" aria-label="Purchases">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <circle cx="9" cy="19" r="1.5"></circle>
                            <circle cx="17" cy="19" r="1.5"></circle>
                            <path d="M3 5h2l2 9h10l2-6H8"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Purchases</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canViewPurchaseList)
                        <a href="{{ route('purchases.index') }}" class="{{ request()->routeIs('purchases.index') ? 'active-sublink' : '' }}">All Purchases</a>
                    @endif
                    @if ($canCreatePurchases)
                        <a href="{{ route('purchases.create') }}" class="{{ request()->routeIs('purchases.create') ? 'active-sublink' : '' }}">New Purchase</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($stockEnabled)
            <a href="{{ route('stock.index') }}" class="menu-link {{ $stockOpen ? 'active-link' : '' }}" data-tooltip="Stock" aria-label="Stock">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 4 4 8l8 4 8-4-8-4Z"></path>
                        <path d="M4 12l8 4 8-4"></path>
                        <path d="M4 16l8 4 8-4"></path>
                    </svg>
                </span>
                <span class="menu-label">Stock</span>
            </a>
        @endif

        @if ($canViewSuppliers)
            <details class="menu-group" {{ $suppliersOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $suppliersOpen ? 'active-link' : '' }}" data-tooltip="Suppliers" aria-label="Suppliers">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M3 8h10v7H3z"></path>
                            <path d="M13 10h4l3 3v2h-7z"></path>
                            <circle cx="7" cy="18" r="1.5"></circle>
                            <circle cx="18" cy="18" r="1.5"></circle>
                        </svg>
                    </span>
                    <span class="menu-label">Suppliers</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canSupplierList)
                        <a href="{{ route('suppliers.index') }}" class="{{ request()->routeIs('suppliers.index') || request()->routeIs('suppliers.show') || request()->routeIs('suppliers.edit') || request()->routeIs('suppliers.statement') ? 'active-sublink' : '' }}">Suppliers</a>
                    @endif
                    @if ($canSupplierCreate)
                        <a href="{{ route('suppliers.create') }}" class="{{ request()->routeIs('suppliers.create') ? 'active-sublink' : '' }}">Add Supplier</a>
                    @endif
                    @if ($canSupplierPayables)
                        <a href="{{ route('suppliers.payables') }}" class="{{ request()->routeIs('suppliers.payables') || request()->routeIs('suppliers.payments.create') ? 'active-sublink' : '' }}">Payables</a>
                    @endif
                    @if ($canSupplierPayments)
                        <a href="{{ route('suppliers.payments.index') }}" class="{{ request()->routeIs('suppliers.payments.index') ? 'active-sublink' : '' }}">Payments</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($canViewCustomers)
            <details class="menu-group" {{ $customersOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $customersOpen ? 'active-link' : '' }}" data-tooltip="Customers" aria-label="Customers">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <circle cx="9" cy="8" r="3"></circle>
                            <circle cx="17" cy="9" r="2.5"></circle>
                            <path d="M4 18c.7-2.6 2.8-4 5-4s4.3 1.4 5 4"></path>
                            <path d="M14.5 18c.3-1.6 1.4-2.7 3.5-3"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Customers</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canCustomerList)
                        <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.index') || request()->routeIs('customers.show') || request()->routeIs('customers.edit') ? 'active-sublink' : '' }}">Customers</a>
                    @endif
                    @if ($canCustomerCreate)
                        <a href="{{ route('customers.create') }}" class="{{ request()->routeIs('customers.create') ? 'active-sublink' : '' }}">Add Customer</a>
                    @endif
                    @if ($canCustomerReceivables)
                        <a href="{{ route('customers.receivables') }}" class="{{ request()->routeIs('customers.receivables') || request()->routeIs('customers.collections.create') ? 'active-sublink' : '' }}">Receivables</a>
                    @endif
                    @if ($canCustomerCollections)
                        <a href="{{ route('customers.collections.index') }}" class="{{ request()->routeIs('customers.collections.index') ? 'active-sublink' : '' }}">Collections</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($canViewInsurance)
            <details class="menu-group" {{ $insuranceOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $insuranceOpen ? 'active-link' : '' }}" data-tooltip="Insurance" aria-label="Insurance">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 3l7 3v5c0 5-3.2 8.7-7 10-3.8-1.3-7-5-7-10V6l7-3Z"></path>
                            <path d="M8.5 12.5 11 15l4.5-5"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Insurance</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canInsuranceClaims && \Illuminate\Support\Facades\Route::has('insurance.claims.index'))
                        <a href="{{ route('insurance.claims.index') }}" class="{{ request()->routeIs('insurance.claims.*') ? 'active-sublink' : '' }}">Claims Desk</a>
                    @endif
                    @if (($canInsuranceManage || $canInsuranceClaims) && \Illuminate\Support\Facades\Route::has('insurance.batches.index'))
                        <a href="{{ route('insurance.batches.index') }}" class="{{ request()->routeIs('insurance.batches.*') ? 'active-sublink' : '' }}">Claim Batches</a>
                    @endif
                    @if (($canInsuranceManage || $canInsuranceClaims) && \Illuminate\Support\Facades\Route::has('insurance.statements.index'))
                        <a href="{{ route('insurance.statements.index') }}" class="{{ request()->routeIs('insurance.statements.*') ? 'active-sublink' : '' }}">Statements</a>
                    @endif
                    @if (($canInsuranceManage || $canInsuranceClaims) && \Illuminate\Support\Facades\Route::has('insurance.insurers.index'))
                        <a href="{{ route('insurance.insurers.index') }}" class="{{ request()->routeIs('insurance.insurers.*') ? 'active-sublink' : '' }}">Insurers</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($canViewAccounting)
            <details class="menu-group" {{ $accountingOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $accountingOpen ? 'active-link' : '' }}" data-tooltip="Accounting" aria-label="Accounting">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M5 4h14"></path>
                            <path d="M7 8h10"></path>
                            <path d="M7 12h10"></path>
                            <path d="M5 20h14"></path>
                            <path d="M6 4v16"></path>
                            <path d="M18 4v16"></path>
                        </svg>
                    </span>
                    <span class="menu-label">Accounting</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canAccountingHome)
                        <a href="{{ route('accounting.index') }}" class="{{ request()->routeIs('accounting.index') ? 'active-sublink' : '' }}">Overview</a>
                    @endif
                    @if ($canAccountingChart)
                        <a href="{{ route('accounting.chart') }}" class="{{ request()->routeIs('accounting.chart') ? 'active-sublink' : '' }}">Chart Of Accounts</a>
                    @endif
                    @if ($canAccountingLedger)
                        <a href="{{ route('accounting.general-ledger') }}" class="{{ request()->routeIs('accounting.general-ledger') ? 'active-sublink' : '' }}">General Ledger</a>
                    @endif
                    @if ($canAccountingTrialBalance)
                        <a href="{{ route('accounting.trial-balance') }}" class="{{ request()->routeIs('accounting.trial-balance') ? 'active-sublink' : '' }}">Trial Balance</a>
                    @endif
                    @if ($canAccountingJournals)
                        <a href="{{ route('accounting.journals') }}" class="{{ request()->routeIs('accounting.journals') ? 'active-sublink' : '' }}">Journals</a>
                    @endif
                    @if ($canAccountingVouchers)
                        <a href="{{ route('accounting.vouchers') }}" class="{{ request()->routeIs('accounting.vouchers') ? 'active-sublink' : '' }}">Payment Vouchers</a>
                    @endif
                    @if ($canAccountingProfitLoss)
                        <a href="{{ route('accounting.profit-loss') }}" class="{{ request()->routeIs('accounting.profit-loss') ? 'active-sublink' : '' }}">Profit &amp; Loss</a>
                    @endif
                    @if ($canAccountingBalanceSheet)
                        <a href="{{ route('accounting.balance-sheet') }}" class="{{ request()->routeIs('accounting.balance-sheet') ? 'active-sublink' : '' }}">Balance Sheet</a>
                    @endif
                    @if ($canAccountingExpenses)
                        <a href="{{ route('accounting.expenses.index') }}" class="{{ request()->routeIs('accounting.expenses.*') ? 'active-sublink' : '' }}">Expenses</a>
                    @endif
                    @if ($canAccountingFixedAssets)
                        <a href="{{ route('accounting.fixed-assets.index') }}" class="{{ request()->routeIs('accounting.fixed-assets.*') ? 'active-sublink' : '' }}">Fixed Assets</a>
                    @endif
                </div>
            </details>
        @endif

        @if ($reportsEnabled)
            <a href="{{ route('reports.index') }}" class="menu-link {{ request()->routeIs('reports.*') ? 'active-link' : '' }}" data-tooltip="Reports" aria-label="Reports">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M5 19V9"></path>
                        <path d="M12 19V5"></path>
                        <path d="M19 19v-7"></path>
                        <path d="M3 19h18"></path>
                    </svg>
                </span>
                <span class="menu-label">Reports</span>
            </a>
        @endif

        @if ($supportEnabled)
            <a href="{{ route('support.index') }}" class="menu-link {{ request()->routeIs('support.*') ? 'active-link' : '' }}" data-tooltip="Support" aria-label="Support">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M9.1 9a3 3 0 1 1 5.8 1c-.5 1.2-1.8 1.8-2.5 2.5-.5.5-.7 1-.7 1.5"></path>
                        <path d="M12 17.5h.01"></path>
                        <circle cx="12" cy="12" r="9"></circle>
                    </svg>
                </span>
                <span class="menu-label">Support</span>
            </a>
        @endif

        @if ($canViewAdmin)
            <details class="menu-group" {{ $adminOpen ? 'open' : '' }}>
                <summary class="dropdown-summary {{ $adminOpen ? 'active-link' : '' }}" data-tooltip="Administration" aria-label="Administration">
                    <span class="menu-short" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 3v6"></path>
                            <path d="M12 15v6"></path>
                            <path d="M3 12h6"></path>
                            <path d="M15 12h6"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </span>
                    <span class="menu-label">Administration</span>
                    <span class="arrow" aria-hidden="true">&gt;</span>
                </summary>

                <div class="dropdown-links">
                    @if ($canViewPlatform)
                        <a href="{{ route('admin.platform.index') }}" class="{{ request()->routeIs('admin.platform.index') ? 'active-sublink' : '' }}">Owner Workspace</a>
                    @endif
                    @if ($canViewPlatform && \Illuminate\Support\Facades\Route::has('admin.platform.backups.index'))
                        <a href="{{ route('admin.platform.backups.index') }}" class="{{ request()->routeIs('admin.platform.backups.*') ? 'active-sublink' : '' }}">Backups</a>
                    @endif
                    @if ($canViewPlatform && \Illuminate\Support\Facades\Route::has('admin.platform.client-exports.index'))
                        <a href="{{ route('admin.platform.client-exports.index') }}" class="{{ request()->routeIs('admin.platform.client-exports.*') ? 'active-sublink' : '' }}">Client Exports</a>
                    @endif
                    @if ($canManagePlatformClients)
                        <a href="{{ route('admin.platform.clients.index') }}" class="{{ request()->routeIs('admin.platform.clients.*', 'admin.platform.branches.*') ? 'active-sublink' : '' }}">Client Setup</a>
                    @endif
                    @if ($canManageUsers)
                        <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active-sublink' : '' }}">Users</a>
                    @endif
                    @if ($canManageRoles)
                        <a href="{{ route('admin.roles.index') }}" class="{{ request()->routeIs('admin.roles.*') ? 'active-sublink' : '' }}">Roles</a>
                    @endif
                    @if ($canViewAudit)
                        <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'active-sublink' : '' }}">Audit Trail</a>
                    @endif
                    @if ($canManageDataImport && \Illuminate\Support\Facades\Route::has('admin.imports.index'))
                        <a href="{{ route('admin.imports.index') }}" class="{{ request()->routeIs('admin.imports.*') ? 'active-sublink' : '' }}">Data Import</a>
                    @endif
                    @if ($settingsEnabled)
                        <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active-sublink' : '' }}">Settings</a>
                    @endif
                </div>
            </details>
        @endif

        <a href="{{ route('account.password.edit') }}" class="menu-link {{ request()->routeIs('account.password.*') ? 'active-link' : '' }}" data-tooltip="Change Password" aria-label="Change Password">
            <span class="menu-short" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                    <path d="M8 11V8a4 4 0 1 1 8 0v3"></path>
                    <circle cx="12" cy="15.5" r="1"></circle>
                </svg>
            </span>
            <span class="menu-label">Change Password</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" style="margin:0;" data-unsaved-warning="false">
            @csrf
            <button type="submit" class="menu-link menu-button" data-tooltip="Logout" aria-label="Logout">
                <span class="menu-short" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M10 17l5-5-5-5"></path>
                        <path d="M15 12H4"></path>
                        <path d="M20 4v16"></path>
                    </svg>
                </span>
                <span class="menu-label">Logout</span>
            </button>
        </form>
    </nav>
</aside>

<button
    type="button"
    class="mobile-sidebar-toggle"
    id="mobileSidebarToggle"
    onclick="toggleMobileSidebar()"
    aria-label="Open sidebar"
    title="Open menu"
>
    <span class="mobile-sidebar-toggle-bars" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </span>
</button>

<div class="mobile-sidebar-backdrop" id="mobileSidebarBackdrop" onclick="toggleMobileSidebar(false)" hidden></div>
<footer class="app-shell-footer" data-mounted="0" aria-label="Application footer">
    <span>&copy; {{ $currentYear }} KIM SOFTWARE SYSTEMS. All rights reserved.</span>
    <strong>Version {{ $appVersion }}</strong>
</footer>

<button
    type="button"
    class="sidebar-toggle sidebar-toggle-floating"
    id="sidebarExpandButton"
    onclick="toggleSidebar(false)"
    aria-label="Expand sidebar"
    title="Expand sidebar"
>
    <span aria-hidden="true">&rsaquo;</span>
</button>

<div class="sidebar-flyout" id="sidebarFlyout" hidden></div>

<div class="live-expiry-alert" id="liveExpiryAlert" hidden aria-live="polite">
    <div class="live-expiry-alert-card">
        <button type="button" class="live-expiry-alert-close" id="liveExpiryAlertClose" aria-label="Dismiss expiry warning">
            &times;
        </button>
        <div class="live-expiry-alert-kicker" id="liveExpiryAlertKicker"></div>
        <div class="live-expiry-alert-title" id="liveExpiryAlertTitle"></div>
        <div class="live-expiry-alert-body" id="liveExpiryAlertBody"></div>
        <div class="live-expiry-alert-list" id="liveExpiryAlertList"></div>
    </div>
</div>

<div class="live-cash-drawer-alert" id="liveCashDrawerAlert" hidden aria-live="polite">
    <div class="live-cash-drawer-alert-card">
        <button type="button" class="live-cash-drawer-alert-close" id="liveCashDrawerAlertClose" aria-label="Dismiss cash drawer warning">
            &times;
        </button>
        <div class="live-cash-drawer-alert-kicker" id="liveCashDrawerAlertKicker"></div>
        <div class="live-cash-drawer-alert-title" id="liveCashDrawerAlertTitle"></div>
        <div class="live-cash-drawer-alert-body" id="liveCashDrawerAlertBody"></div>
        <div class="live-cash-drawer-alert-list" id="liveCashDrawerAlertList"></div>
    </div>
</div>

<style>
html,
body {
    max-width: 100%;
    overflow-x: hidden;
}

.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    padding: 18px 16px 24px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    color: white;
    background:
        radial-gradient(circle at top, rgba(255, 255, 255, 0.12), transparent 32%),
        linear-gradient(180deg, var(--sidebar-start, #1f7a4f), var(--sidebar-end, #6a1b9a));
    box-shadow: 0 20px 44px rgba(16, 24, 40, 0.18);
    transition: width 0.28s ease, box-shadow 0.28s ease;
    z-index: 1000;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.brand {
    display: flex;
    align-items: center;
    min-width: 0;
    flex: 1;
}

.brand-copy {
    min-width: 0;
    width: 100%;
}

.brand h2 {
    margin: 0;
    font-size: 24px;
    line-height: 1.05;
    letter-spacing: -0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.brand p {
    margin: 5px 0 0;
    font-size: 13px;
    opacity: 0.82;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.menu {
    display: flex;
    flex-direction: column;
    gap: 10px;
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.28) transparent;
}

.menu::-webkit-scrollbar {
    width: 8px;
}

.menu::-webkit-scrollbar-track {
    background: transparent;
}

.menu::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 999px;
}

.menu::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.32);
}

.owner-badge {
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
    margin: 0 0 16px;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.16);
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sidebar-alert {
    margin: 14px 0 18px;
    padding: 14px 14px 12px;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.14);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.sidebar-alert-warning {
    background: linear-gradient(180deg, rgba(255, 244, 214, 0.22), rgba(255, 255, 255, 0.12));
}

.sidebar-alert-cash {
    background: linear-gradient(180deg, rgba(220, 252, 231, 0.24), rgba(255, 255, 255, 0.12));
}

.sidebar-alert-title {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.sidebar-alert-body {
    margin-top: 8px;
    font-size: 12px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.92);
}

.sidebar-alert-list {
    display: grid;
    gap: 8px;
    margin-top: 12px;
}

.sidebar-alert-item {
    display: grid;
    gap: 2px;
    font-size: 11px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.sidebar-alert-item strong {
    font-size: 12px;
}

.sidebar-alert-item span {
    color: rgba(255, 255, 255, 0.78);
    line-height: 1.45;
}

.menu-group {
    margin: 0;
}

.menu-link,
.dropdown-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 12px 14px;
    border-radius: 14px;
    color: white;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.menu-button {
    border: none;
    text-align: left;
    font-family: inherit;
}

.menu-link:hover,
.dropdown-summary:hover {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.16);
    transform: translateX(1px);
}

.active-link {
    background: rgba(255, 255, 255, 0.22) !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
}

.menu-label {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.menu-short {
    display: none;
    align-items: center;
    justify-content: center;
    min-width: 30px;
    height: 30px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.14);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
}

.menu-short svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    fill: none;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.menu-hint {
    font-size: 11px;
    opacity: 0.7;
}

.dropdown-summary {
    list-style: none;
}

.dropdown-summary::-webkit-details-marker {
    display: none;
}

.arrow {
    font-size: 18px;
    line-height: 1;
    transition: transform 0.2s ease;
}

details[open] > .dropdown-summary .arrow {
    transform: rotate(90deg);
}

.dropdown-links {
    margin-top: 8px;
    padding-left: 14px;
    display: grid;
    gap: 6px;
}

.dropdown-links a {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-radius: 10px;
    color: white;
    text-decoration: none;
    font-size: 13px;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid transparent;
    transition: background 0.2s ease, border-color 0.2s ease;
}

.dropdown-links a:hover {
    background: rgba(255, 255, 255, 0.14);
    border-color: rgba(255, 255, 255, 0.14);
}

.active-sublink {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.18) !important;
    font-weight: 700;
}

.disabled-link {
    opacity: 0.55;
    cursor: not-allowed;
}

.sidebar-toggle {
    border: none;
    background: rgba(255, 255, 255, 0.14);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 20px;
    transition: background 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    box-shadow: 0 10px 24px rgba(16, 24, 40, 0.18);
}

.sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.22);
    transform: translateY(-1px);
}

.sidebar-toggle-floating {
    position: fixed;
    top: 18px;
    left: 22px;
    width: 34px;
    height: 34px;
    border-radius: 999px;
    font-size: 18px;
    opacity: 0;
    pointer-events: none;
    z-index: 1150;
}

.sidebar.collapsed ~ .sidebar-toggle-floating {
    opacity: 1;
    pointer-events: auto;
}

.sidebar.collapsed .brand-copy,
.sidebar.collapsed .arrow,
.sidebar.collapsed .dropdown-links,
.sidebar.collapsed .menu-hint,
.sidebar.collapsed .owner-badge,
.sidebar.collapsed .sidebar-alert {
    display: none;
}

.sidebar.collapsed .sidebar-header {
    display: none;
}

.sidebar.collapsed .brand {
    flex: none;
    justify-content: center;
}

.sidebar.collapsed .sidebar-toggle-inline,
.sidebar.collapsed .menu-label {
    display: none;
}

.sidebar.collapsed .menu-short {
    display: inline-flex;
}

.sidebar.collapsed .menu {
    padding-top: 78px;
}

.sidebar.collapsed .menu-link,
.sidebar.collapsed .dropdown-summary {
    justify-content: center;
    padding-left: 0;
    padding-right: 0;
    position: relative;
    overflow: visible;
}

.sidebar-flyout {
    position: fixed;
    min-width: 220px;
    max-width: 280px;
    padding: 10px;
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.96);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 20px 44px rgba(15, 23, 42, 0.26);
    z-index: 1300;
}

.sidebar-flyout[hidden] {
    display: none;
}

.sidebar-flyout::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 22px;
    width: 12px;
    height: 12px;
    background: rgba(15, 23, 42, 0.96);
    border-left: 1px solid rgba(255, 255, 255, 0.12);
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    transform: rotate(45deg);
}

.sidebar-flyout-label,
.sidebar-flyout-title {
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.sidebar-flyout-label {
    padding: 4px 6px;
}

.sidebar-flyout-title {
    padding: 4px 6px 10px;
    margin-bottom: 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-flyout-links {
    display: grid;
    gap: 6px;
}

.sidebar-flyout-link {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 10px;
    color: #fff;
    text-decoration: none;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 700;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.sidebar-flyout-link:hover {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.16);
    transform: translateX(1px);
}

.sidebar-flyout-link.active {
    background: rgba(255, 255, 255, 0.22);
    border-color: rgba(255, 255, 255, 0.18);
}

.app-shell-footer {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
    width: 100%;
    margin-top: 24px;
    padding: 12px 16px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(15, 138, 148, 0.96), rgba(12, 109, 117, 0.96));
    border: 1px solid rgba(15, 138, 148, 0.34);
    box-shadow: 0 14px 30px rgba(12, 109, 117, 0.18);
    color: rgba(255, 255, 255, 0.94);
    font-size: 12px;
    backdrop-filter: blur(8px);
    box-sizing: border-box;
    text-align: center;
    min-height: 52px;
}

.app-shell-footer span {
    flex: 1;
    text-align: center;
}

.app-shell-footer strong {
    position: absolute;
    right: 16px;
    color: #ffffff;
    white-space: nowrap;
}

.app-shell-footer[data-mounted="0"] {
    display: none;
}

.live-expiry-alert {
    position: fixed;
    top: 22px;
    right: 22px;
    width: min(380px, calc(100vw - 36px));
    z-index: 1450;
}

.live-expiry-alert[hidden] {
    display: none;
}

.live-expiry-alert-card {
    position: relative;
    padding: 18px 18px 16px;
    border-radius: 20px;
    color: #0f172a;
    background: linear-gradient(180deg, rgba(255, 251, 235, 0.98), rgba(255, 244, 214, 0.98));
    border: 1px solid rgba(217, 119, 6, 0.22);
    box-shadow: 0 22px 48px rgba(15, 23, 42, 0.18);
}

.live-expiry-alert-kicker {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #b45309;
}

.live-expiry-alert-title {
    margin-top: 8px;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
}

.live-expiry-alert-body {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
}

.live-expiry-alert-list {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.live-expiry-alert-item {
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(217, 119, 6, 0.12);
}

.live-expiry-alert-item strong {
    display: block;
    font-size: 13px;
}

.live-expiry-alert-item span {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
}

.live-expiry-alert-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 999px;
    color: #475569;
    background: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    font-size: 24px;
    line-height: 1;
}

.live-expiry-alert-close:hover {
    background: rgba(255, 255, 255, 0.92);
}

.live-cash-drawer-alert {
    position: fixed;
    right: 22px;
    bottom: 22px;
    width: min(380px, calc(100vw - 36px));
    z-index: 1450;
}

.live-cash-drawer-alert[hidden] {
    display: none;
}

.live-cash-drawer-alert-card {
    position: relative;
    padding: 18px 18px 16px;
    border-radius: 20px;
    color: #0f172a;
    background: linear-gradient(180deg, rgba(236, 253, 243, 0.98), rgba(220, 252, 231, 0.98));
    border: 1px solid rgba(6, 118, 71, 0.22);
    box-shadow: 0 22px 48px rgba(15, 23, 42, 0.18);
}

.live-cash-drawer-alert-kicker {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #067647;
}

.live-cash-drawer-alert-title {
    margin-top: 8px;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.2;
}

.live-cash-drawer-alert-body {
    margin-top: 8px;
    font-size: 13px;
    line-height: 1.55;
    color: #475569;
}

.live-cash-drawer-alert-list {
    display: grid;
    gap: 8px;
    margin-top: 14px;
}

.live-cash-drawer-alert-item {
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid rgba(6, 118, 71, 0.12);
}

.live-cash-drawer-alert-item strong {
    display: block;
    font-size: 13px;
}

.live-cash-drawer-alert-item span {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
}

.live-cash-drawer-alert-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 34px;
    height: 34px;
    border: none;
    border-radius: 999px;
    color: #475569;
    background: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    font-size: 24px;
    line-height: 1;
}

.live-cash-drawer-alert-close:hover {
    background: rgba(255, 255, 255, 0.92);
}

#mainContent {
    position: relative;
    margin-left: 260px;
    width: calc(100vw - 260px);
    max-width: calc(100vw - 260px);
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    padding: 20px clamp(16px, 2vw, 28px) 24px !important;
    overflow-x: hidden;
    border-left: 1px solid rgba(15, 23, 42, 0.06);
    transition: margin-left 0.28s ease, width 0.28s ease, max-width 0.28s ease;
}

#mainContent > .app-shell-footer {
    margin-top: auto;
    flex-shrink: 0;
}
#mainContent > * {
    max-width: 100%;
}

#mainContent .topbar,
#mainContent .panel {
    max-width: 100%;
}

#mainContent .items-table-wrap,
#mainContent .table-wrap,
#mainContent .search-results-wrap {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
}

#mainContent {
    scrollbar-gutter: stable both-edges;
}

#mainContent.expanded {
    margin-left: 80px;
    width: calc(100vw - 80px);
    max-width: calc(100vw - 80px);
}

@media (max-width: 900px) {
    .sidebar {
        position: static;
        width: 100%;
        height: auto;
        box-shadow: none;
        overflow: visible;
    }

    .sidebar.collapsed {
        width: 100%;
    }

    .menu {
        overflow: visible;
        padding-right: 0;
    }

    .sidebar-toggle-inline,
    .sidebar-toggle-floating {
        display: none !important;
    }

    .sidebar .brand-copy,
    .sidebar .menu-label,
    .sidebar .arrow,
    .sidebar .dropdown-links,
    .sidebar .menu-hint {
        display: initial;
    }

    .app-shell-footer {
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
        min-height: 0;
    }

    .app-shell-footer span {
        text-align: left;
    }

    .app-shell-footer strong {
        position: static;
    }

    .sidebar.mobile-open .sidebar-header {
        margin-bottom: 14px;
    }

    .sidebar.mobile-open .brand h2 {
        font-size: 20px;
    }

    .sidebar.mobile-open .brand p {
        font-size: 11px;
    }

    .sidebar.mobile-open .menu {
        gap: 7px;
    }

    .sidebar.mobile-open .menu-link,
    .sidebar.mobile-open .dropdown-summary {
        min-height: 40px;
        padding: 10px 11px !important;
        border-radius: 12px;
        font-size: 13px;
    }

    .sidebar.mobile-open .dropdown-links {
        gap: 5px;
        margin-top: 6px;
        padding-left: 10px;
    }

    .sidebar.mobile-open .dropdown-links a {
        padding: 9px 10px;
        border-radius: 9px;
        font-size: 12px;
    }
    #mainContent,
    #mainContent.expanded {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border-left: none;
        padding: 16px !important;
    }

    .sidebar-flyout {
        display: none;
    }

    .live-expiry-alert {
        top: 12px;
        right: 12px;
        width: calc(100vw - 24px);
    }

    .live-cash-drawer-alert {
        right: 12px;
        bottom: 12px;
        width: calc(100vw - 24px);
    }
}

.mobile-sidebar-toggle,
.mobile-sidebar-backdrop {
    display: none;
}

@media (max-width: 900px) {
    body.mobile-sidebar-open {
        overflow: hidden;
    }

    .mobile-sidebar-toggle {
        position: fixed;
        top: 14px;
        left: 14px;
        z-index: 1300;
        width: 44px;
        height: 44px;
        border: none;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(180deg, var(--sidebar-start, #1f7a4f), var(--sidebar-end, #6a1b9a));
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.24);
        cursor: pointer;
        transition: opacity 0.18s ease, visibility 0.18s ease, transform 0.18s ease;
    }

    body.mobile-sidebar-open .mobile-sidebar-toggle {
        opacity: 0;
        pointer-events: none;
        visibility: hidden;
    }
    .mobile-sidebar-toggle-bars {
        display: grid;
        gap: 4px;
    }

    .mobile-sidebar-toggle-bars span {
        display: block;
        width: 21px;
        height: 2px;
        border-radius: 999px;
        background: #fff;
    }

    .mobile-sidebar-backdrop {
        position: fixed;
        inset: 0;
        z-index: 1090;
        display: block;
        background: rgba(15, 23, 42, 0.48);
        backdrop-filter: blur(2px);
    }

    .mobile-sidebar-backdrop[hidden] {
        display: none !important;
    }

    .sidebar {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: min(80vw, 282px) !important;
        height: 100vh !important;
        height: 100dvh !important;
        max-height: 100dvh;
        padding: 14px 12px 18px !important;
        overflow: hidden !important;
        transform: translateX(-108%);
        transition: transform 0.28s ease, box-shadow 0.28s ease !important;
        box-shadow: 0 24px 56px rgba(15, 23, 42, 0.32) !important;
        z-index: 1200 !important;
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar.collapsed {
        width: min(80vw, 282px) !important;
    }

    .sidebar .menu,
    .sidebar.collapsed .menu {
        overflow-y: auto !important;
        overflow-x: hidden !important;
        padding-top: 0 !important;
        padding-right: 4px !important;
    }

    .sidebar-toggle-inline {
        display: inline-flex !important;
    }

    .sidebar-toggle-floating {
        display: none !important;
    }

    .sidebar.collapsed .sidebar-header {
        display: flex !important;
    }

    .sidebar.collapsed .brand {
        flex: 1 !important;
        justify-content: flex-start !important;
    }

    .sidebar.collapsed .brand-copy,
    .sidebar.collapsed .menu-hint,
    .sidebar.collapsed .owner-badge,
    .sidebar.collapsed .sidebar-alert {
        display: block !important;
    }

    .sidebar.collapsed .menu-label,
    .sidebar.collapsed .arrow {
        display: inline !important;
    }

    .sidebar.collapsed .dropdown-links {
        display: grid !important;
    }

    .sidebar.collapsed .menu-short {
        display: none !important;
    }

    .sidebar.collapsed .menu-link,
    .sidebar.collapsed .dropdown-summary {
        justify-content: space-between !important;
        padding: 12px 14px !important;
        position: static !important;
        overflow: hidden !important;
    }

    #mainContent,
    #mainContent.expanded {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        border-left: none !important;
        padding: 72px 12px 16px !important;
    }
}</style>

<script>
(() => {
    const storageKey = 'kimrx.sidebar.collapsed';
    const expiryReminderConfig = @json($expiryAlertConfig);
    const cashDrawerAlertConfig = @json($cashDrawerAlertConfig);
    let hideFlyoutTimer = null;
    let activeFlyoutTrigger = null;
    let expiryReminderTimer = null;
    let lastExpiryReminderCheckAt = 0;
    let cashDrawerAlertTimer = null;
    let lastCashDrawerAlertCheckAt = 0;

    function syncSidebarState(collapsed) {
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('mainContent');
        const collapseButton = document.getElementById('sidebarCollapseButton');
        const expandButton = document.getElementById('sidebarExpandButton');
        const flyout = document.getElementById('sidebarFlyout');

        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('collapsed', collapsed);

        if (content) {
            content.classList.toggle('expanded', collapsed);
        }

        if (collapseButton) {
            collapseButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        if (expandButton) {
            expandButton.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
        }

        if (flyout) {
            flyout.hidden = true;
            flyout.innerHTML = '';
        }

        activeFlyoutTrigger = null;
    }

    function sidebarIsCollapsed() {
        const sidebar = document.getElementById('sidebar');

        return !!sidebar && sidebar.classList.contains('collapsed') && window.innerWidth > 900;
    }

    function mobileSidebarMode() {
        return window.matchMedia('(max-width: 900px)').matches;
    }

    function setMobileSidebarOpen(open) {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');
        const toggle = document.getElementById('mobileSidebarToggle');

        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('mobile-open', open);
        document.body.classList.toggle('mobile-sidebar-open', open);

        if (backdrop) {
            backdrop.hidden = !open;
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close sidebar' : 'Open sidebar');
            toggle.setAttribute('title', open ? 'Close menu' : 'Open menu');
        }

        if (!open) {
            hideFlyout();
        }
    }

    window.toggleMobileSidebar = function (forceOpen) {
        const sidebar = document.getElementById('sidebar');

        if (!sidebar) {
            return;
        }

        const nextOpen = typeof forceOpen === 'boolean'
            ? forceOpen
            : !sidebar.classList.contains('mobile-open');

        setMobileSidebarOpen(nextOpen);
    };
    function clearFlyoutTimer() {
        if (hideFlyoutTimer) {
            window.clearTimeout(hideFlyoutTimer);
            hideFlyoutTimer = null;
        }
    }

    function hideFlyout() {
        const flyout = document.getElementById('sidebarFlyout');

        clearFlyoutTimer();

        if (!flyout) {
            return;
        }

        flyout.hidden = true;
        flyout.innerHTML = '';
        activeFlyoutTrigger = null;
    }

    function scheduleFlyoutHide() {
        clearFlyoutTimer();
        hideFlyoutTimer = window.setTimeout(hideFlyout, 120);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatLiveMoney(value, currencySymbol = 'UGX') {
        const amount = Number(value || 0);

        return `${currencySymbol} ${amount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    }

    function hideLiveExpiryAlert() {
        const root = document.getElementById('liveExpiryAlert');

        if (!root) {
            return;
        }

        root.hidden = true;
    }

    function renderLiveExpiryAlert(warning) {
        const root = document.getElementById('liveExpiryAlert');
        const kicker = document.getElementById('liveExpiryAlertKicker');
        const title = document.getElementById('liveExpiryAlertTitle');
        const body = document.getElementById('liveExpiryAlertBody');
        const list = document.getElementById('liveExpiryAlertList');

        if (!root || !kicker || !title || !body || !list || !warning || Number(warning.count || 0) <= 0) {
            return;
        }

        const count = Number(warning.count || 0);
        kicker.textContent = 'Expiry Warning';
        title.textContent = 'Soon expiring stock needs attention.';
        body.textContent = `${count.toLocaleString()} batch${count === 1 ? '' : 'es'} need attention. Dispense the nearest-expiry stock first.`;

        const items = Array.isArray(warning.items) ? warning.items : [];
        list.innerHTML = items.map((item) => {
            const parts = [item.batch_number];

            if (item.strength) {
                parts.push(item.strength);
            }

            if (item.risk_label) {
                parts.push(item.risk_label);
            }

            return `
                <div class="live-expiry-alert-item">
                    <strong>${escapeHtml(item.product_name || 'Unknown Product')}</strong>
                    <span>${escapeHtml(parts.filter(Boolean).join(' | '))}</span>
                </div>
            `;
        }).join('');

        root.hidden = false;
    }

    function hideLiveCashDrawerAlert() {
        const root = document.getElementById('liveCashDrawerAlert');

        if (!root) {
            return;
        }

        root.hidden = true;
    }

    function renderLiveCashDrawerAlert(warning) {
        const root = document.getElementById('liveCashDrawerAlert');
        const kicker = document.getElementById('liveCashDrawerAlertKicker');
        const title = document.getElementById('liveCashDrawerAlertTitle');
        const body = document.getElementById('liveCashDrawerAlertBody');
        const list = document.getElementById('liveCashDrawerAlertList');

        if (!root || !kicker || !title || !body || !list || !warning || Number(warning.count || 0) <= 0) {
            return;
        }

        const currency = warning.currency_symbol || 'UGX';
        kicker.textContent = 'Cash Drawer Alert';
        title.textContent = 'Drawer threshold reached.';
        body.textContent = `Tracked drawer balance is ${formatLiveMoney(warning.current_balance, currency)}. Threshold is ${formatLiveMoney(warning.alert_threshold, currency)}. Record a draw with a clear reason.`;

        const items = [
            ['Opening Balance', warning.opening_balance],
            ['Cash POS Sales', warning.cash_sales_total],
            ['Cash Collections', warning.cash_collections_total],
            ['Documented Draws', warning.draws_total],
        ];

        list.innerHTML = items.map(([label, amount]) => `
            <div class="live-cash-drawer-alert-item">
                <strong>${escapeHtml(label)}</strong>
                <span>${escapeHtml(formatLiveMoney(amount, currency))}</span>
            </div>
        `).join('');

        root.hidden = false;
    }

    function normalizedReminderHours() {
        if (!expiryReminderConfig || !Array.isArray(expiryReminderConfig.hours)) {
            return [];
        }

        return expiryReminderConfig.hours
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value) && value >= 0 && value <= 23)
            .sort((left, right) => left - right);
    }

    function nextExpiryReminderAt() {
        const hours = normalizedReminderHours();

        if (!hours.length) {
            return null;
        }

        const now = new Date();

        for (const hour of hours) {
            const candidate = new Date(now);
            candidate.setHours(hour, 0, 0, 0);

            if (candidate.getTime() > now.getTime()) {
                return candidate;
            }
        }

        const nextDay = new Date(now);
        nextDay.setDate(nextDay.getDate() + 1);
        nextDay.setHours(hours[0], 0, 0, 0);

        return nextDay;
    }

    function scheduleExpiryReminderCheck() {
        if (expiryReminderTimer) {
            window.clearTimeout(expiryReminderTimer);
            expiryReminderTimer = null;
        }

        if (!expiryReminderConfig?.enabled || !expiryReminderConfig?.endpoint) {
            return;
        }

        const nextAt = nextExpiryReminderAt();

        if (!nextAt) {
            return;
        }

        const delay = Math.max(1000, nextAt.getTime() - Date.now() + 300);

        expiryReminderTimer = window.setTimeout(() => {
            requestExpiryReminder(true);
        }, delay);
    }

    function requestExpiryReminder(force = false) {
        if (!expiryReminderConfig?.enabled || !expiryReminderConfig?.endpoint || !window.fetch) {
            return Promise.resolve();
        }

        const now = Date.now();

        if (!force && now - lastExpiryReminderCheckAt < 30000) {
            return Promise.resolve();
        }

        lastExpiryReminderCheckAt = now;

        return window.fetch(expiryReminderConfig.endpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then((payload) => {
                if (payload?.available && payload.warning) {
                    renderLiveExpiryAlert(payload.warning);
                }
            })
            .catch(() => {
            })
            .finally(() => {
                scheduleExpiryReminderCheck();
            });
    }

    function scheduleCashDrawerReminderCheck() {
        if (cashDrawerAlertTimer) {
            window.clearTimeout(cashDrawerAlertTimer);
            cashDrawerAlertTimer = null;
        }

        if (!cashDrawerAlertConfig?.enabled || !cashDrawerAlertConfig?.endpoint) {
            return;
        }

        const pollSeconds = Math.max(15, Number(cashDrawerAlertConfig.pollSeconds || 60));

        cashDrawerAlertTimer = window.setTimeout(() => {
            requestCashDrawerReminder(true);
        }, pollSeconds * 1000);
    }

    function requestCashDrawerReminder(force = false) {
        if (!cashDrawerAlertConfig?.enabled || !cashDrawerAlertConfig?.endpoint || !window.fetch) {
            return Promise.resolve();
        }

        const now = Date.now();

        if (!force && now - lastCashDrawerAlertCheckAt < 30000) {
            return Promise.resolve();
        }

        lastCashDrawerAlertCheckAt = now;

        return window.fetch(cashDrawerAlertConfig.endpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => {
                if (!response.ok) {
                    return null;
                }

                return response.json();
            })
            .then((payload) => {
                if (payload?.available && payload.warning) {
                    renderLiveCashDrawerAlert(payload.warning);
                }
            })
            .catch(() => {
            })
            .finally(() => {
                scheduleCashDrawerReminderCheck();
            });
    }

    function positionFlyout(trigger, flyout) {
        const rect = trigger.getBoundingClientRect();
        const top = Math.max(12, Math.min(rect.top, window.innerHeight - flyout.offsetHeight - 12));

        flyout.style.top = `${top}px`;
        flyout.style.left = `${rect.right + 14}px`;
    }

    function buildFlyoutContent(trigger) {
        const label = trigger.querySelector('.menu-label')?.textContent?.trim()
            || trigger.getAttribute('data-tooltip')
            || 'Menu';

        if (!trigger.classList.contains('dropdown-summary')) {
            return `<div class="sidebar-flyout-label">${escapeHtml(label)}</div>`;
        }

        const group = trigger.parentElement;
        const links = group ? Array.from(group.querySelectorAll('.dropdown-links a')) : [];

        const linkMarkup = links.map((link) => {
            const activeClass = link.classList.contains('active-sublink') ? ' active' : '';

            return `<a href="${link.href}" class="sidebar-flyout-link${activeClass}">${escapeHtml(link.textContent.trim())}</a>`;
        }).join('');

        return `
            <div class="sidebar-flyout-title">${escapeHtml(label)}</div>
            <div class="sidebar-flyout-links">${linkMarkup || `<div class="sidebar-flyout-label">No actions</div>`}</div>
        `;
    }

    function showFlyout(trigger) {
        const flyout = document.getElementById('sidebarFlyout');

        if (!flyout || !sidebarIsCollapsed()) {
            hideFlyout();
            return;
        }

        clearFlyoutTimer();
        flyout.innerHTML = buildFlyoutContent(trigger);
        flyout.hidden = false;
        activeFlyoutTrigger = trigger;
        positionFlyout(trigger, flyout);
    }

    function bindFlyoutInteractions() {
        const flyout = document.getElementById('sidebarFlyout');

        if (!flyout) {
            return;
        }

        flyout.addEventListener('mouseenter', () => {
            clearFlyoutTimer();
        });

        flyout.addEventListener('mouseleave', () => {
            scheduleFlyoutHide();
        });

        document.querySelectorAll('#sidebar .menu-link, #sidebar .dropdown-summary').forEach((trigger) => {
            trigger.addEventListener('mouseenter', () => {
                showFlyout(trigger);
            });

            trigger.addEventListener('mouseleave', () => {
                scheduleFlyoutHide();
            });
        });
    }

    window.toggleSidebar = function (forceCollapsed) {
        const sidebar = document.getElementById('sidebar');

        if (!sidebar) {
            return;
        }

        if (mobileSidebarMode()) {
            const nextOpen = typeof forceCollapsed === 'boolean'
                ? !forceCollapsed
                : !sidebar.classList.contains('mobile-open');

            setMobileSidebarOpen(nextOpen);
            return;
        }

        const nextCollapsed = typeof forceCollapsed === 'boolean'
            ? forceCollapsed
            : !sidebar.classList.contains('collapsed');

        syncSidebarState(nextCollapsed);

        try {
            window.localStorage.setItem(storageKey, nextCollapsed ? '1' : '0');
        } catch (error) {
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        let collapsed = false;
        const liveExpiryAlertClose = document.getElementById('liveExpiryAlertClose');
        const liveCashDrawerAlertClose = document.getElementById('liveCashDrawerAlertClose');
        const shellFooter = document.querySelector('.app-shell-footer');
        const mainContent = document.getElementById('mainContent');

        try {
            collapsed = window.localStorage.getItem(storageKey) === '1';
        } catch (error) {
        }

        syncSidebarState(collapsed);
        setMobileSidebarOpen(false);
        bindFlyoutInteractions();

        if (shellFooter && mainContent) {
            mainContent.appendChild(shellFooter);
            shellFooter.dataset.mounted = '1';
        }

        document.querySelectorAll('#sidebar .dropdown-summary').forEach((summary) => {
            summary.addEventListener('click', (event) => {
                const sidebar = document.getElementById('sidebar');

                if (!sidebar || !sidebar.classList.contains('collapsed') || mobileSidebarMode()) {
                    return;
                }

                event.preventDefault();

                const group = summary.parentElement;
                window.toggleSidebar(false);

                if (group && group.tagName === 'DETAILS') {
                    group.open = true;
                }
            });
        });

        document.querySelectorAll('#sidebar .menu-link').forEach((link) => {
            link.addEventListener('click', () => {
                if (mobileSidebarMode()) {
                    setMobileSidebarOpen(false);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMobileSidebarOpen(false);
            }
        });

        if (liveExpiryAlertClose) {
            liveExpiryAlertClose.addEventListener('click', () => {
                hideLiveExpiryAlert();
            });
        }

        if (liveCashDrawerAlertClose) {
            liveCashDrawerAlertClose.addEventListener('click', () => {
                hideLiveCashDrawerAlert();
            });
        }

        if (expiryReminderConfig?.initialWarning) {
            renderLiveExpiryAlert(expiryReminderConfig.initialWarning);
        }

        if (cashDrawerAlertConfig?.initialWarning) {
            renderLiveCashDrawerAlert(cashDrawerAlertConfig.initialWarning);
        }

        scheduleExpiryReminderCheck();
        scheduleCashDrawerReminderCheck();

        window.addEventListener('resize', () => {
            if (!mobileSidebarMode()) {
                setMobileSidebarOpen(false);
            }

            if (!sidebarIsCollapsed()) {
                hideFlyout();
            } else {
                const flyout = document.getElementById('sidebarFlyout');

                if (flyout && !flyout.hidden && activeFlyoutTrigger) {
                    positionFlyout(activeFlyoutTrigger, flyout);
                }
            }
        });

        document.addEventListener('scroll', () => {
            const flyout = document.getElementById('sidebarFlyout');

            if (flyout && !flyout.hidden && activeFlyoutTrigger) {
                positionFlyout(activeFlyoutTrigger, flyout);
            }
        }, true);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                requestExpiryReminder();
                requestCashDrawerReminder();
            }
        });

        window.addEventListener('focus', () => {
            requestExpiryReminder();
            requestCashDrawerReminder();
        });
    });
})();
</script>

@include('layouts.unsaved-changes-script')
