<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CashDrawerController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryAlertController;
use App\Http\Controllers\InsuranceController;
use App\Http\Controllers\PlatformBackupController;
use App\Http\Controllers\PlatformClientController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleManagementController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SupplierAccountController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'user.context'])->group(function () {
    Route::get('/account/password', [AuthController::class, 'showChangePassword'])->name('account.password.edit');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('account.password.update');

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/alerts/expiry-reminder', [InventoryAlertController::class, 'expiryReminder'])
        ->name('alerts.expiry-reminder');
    Route::get('/alerts/cash-drawer', [CashDrawerController::class, 'reminder'])
        ->name('alerts.cash-drawer');
    Route::get('/support', [SupportController::class, 'index'])
        ->name('support.index');

    /*
    |--------------------------------------------------------------------------
    | PRODUCTS
    |--------------------------------------------------------------------------
    */
    Route::get('/products', [ProductController::class, 'index'])
        ->middleware('permission:products.view')
        ->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:products.create')
        ->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:products.create')
        ->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:products.edit')
        ->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:products.edit')
        ->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')
        ->name('products.destroy');
    Route::get('/products/{product}/sources', [ProductController::class, 'sources'])
        ->middleware('permission:products.view')
        ->name('products.sources');

    /*
    |--------------------------------------------------------------------------
    | CATEGORIES
    |--------------------------------------------------------------------------
    */
    Route::get('/categories', [CategoryController::class, 'index'])
        ->middleware('permission:categories.view')
        ->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])
        ->middleware('permission:categories.manage')
        ->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])
        ->middleware('permission:categories.manage')
        ->name('categories.store');
    Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])
        ->middleware('permission:categories.manage')
        ->name('categories.edit');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])
        ->middleware('permission:categories.manage')
        ->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
        ->middleware('permission:categories.manage')
        ->name('categories.destroy');

    /*
    |--------------------------------------------------------------------------
    | UNITS
    |--------------------------------------------------------------------------
    */
    Route::get('/units', [UnitController::class, 'index'])
        ->middleware('permission:units.view')
        ->name('units.index');
    Route::get('/units/create', [UnitController::class, 'create'])
        ->middleware('permission:units.manage')
        ->name('units.create');
    Route::post('/units', [UnitController::class, 'store'])
        ->middleware('permission:units.manage')
        ->name('units.store');
    Route::get('/units/{unit}/edit', [UnitController::class, 'edit'])
        ->middleware('permission:units.manage')
        ->name('units.edit');
    Route::put('/units/{unit}', [UnitController::class, 'update'])
        ->middleware('permission:units.manage')
        ->name('units.update');
    Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
        ->middleware('permission:units.manage')
        ->name('units.destroy');

    /*
    |--------------------------------------------------------------------------
    | PURCHASES
    |--------------------------------------------------------------------------
    */
    Route::get('/purchases', [PurchaseController::class, 'index'])
        ->middleware('permission:purchases.view')
        ->name('purchases.index');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])
        ->middleware('permission:purchases.create')
        ->name('purchases.create');
    Route::post('/purchases', [PurchaseController::class, 'store'])
        ->middleware('permission:purchases.create')
        ->name('purchases.store');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware('permission:purchases.view')
        ->name('purchases.show');
    Route::get('/purchases/{purchase}/edit', [PurchaseController::class, 'edit'])
        ->middleware('permission:purchases.edit')
        ->name('purchases.edit');
    Route::put('/purchases/{purchase}', [PurchaseController::class, 'update'])
        ->middleware('permission:purchases.edit')
        ->name('purchases.update');
    Route::get('/purchases/{purchase}/items/{item}/correct', [PurchaseController::class, 'correctItem'])
        ->middleware('permission:purchases.correct_items')
        ->name('purchases.items.correct');
    Route::put('/purchases/{purchase}/items/{item}/correct', [PurchaseController::class, 'updateCorrectedItem'])
        ->middleware('permission:purchases.correct_items')
        ->name('purchases.items.updateCorrection');
    Route::get('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])
        ->middleware('permission:purchases.receive')
        ->name('purchases.receive');
    Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'storeReceive'])
        ->middleware('permission:purchases.receive')
        ->name('purchases.storeReceive');
    Route::get('/purchases/{purchase}/add-items', [PurchaseController::class, 'addItems'])
        ->middleware('permission:purchases.add_items')
        ->name('purchases.add-items');
    Route::post('/purchases/{purchase}/add-items', [PurchaseController::class, 'storeAddedItems'])
        ->middleware('permission:purchases.add_items')
        ->name('purchases.storeAddedItems');
    Route::get('/products/{product}/purchase-data', [PurchaseController::class, 'productPurchaseData'])
        ->middleware('permission:purchases.create,purchases.edit,purchases.add_items')
        ->name('products.purchase-data');

    /*
    |--------------------------------------------------------------------------
    | SALES
    |--------------------------------------------------------------------------
    */
    Route::get('/sales', [SaleController::class, 'index'])
        ->middleware('permission:sales.view')
        ->name('sales.index');
    Route::get('/sales/create', [SaleController::class, 'create'])
        ->middleware('permission:sales.create')
        ->name('sales.create');
    Route::post('/sales', [SaleController::class, 'store'])
        ->middleware('permission:sales.create')
        ->name('sales.store');
    Route::get('/sales/proforma', [SaleController::class, 'proforma'])
        ->middleware('permission:sales.proforma')
        ->name('sales.proforma');
    Route::get('/sales/proforma/create', [SaleController::class, 'createProforma'])
        ->middleware('permission:sales.proforma')
        ->name('sales.proforma.create');
    Route::post('/sales/proforma', [SaleController::class, 'storeProforma'])
        ->middleware('permission:sales.proforma')
        ->name('sales.proforma.store');
    Route::get('/sales/pending', [SaleController::class, 'pending'])
        ->middleware('permission:sales.view_pending')
        ->name('sales.pending');
    Route::get('/sales/approved', [SaleController::class, 'approved'])
        ->middleware('permission:sales.view_approved')
        ->name('sales.approved');
    Route::get('/sales/cancelled', [SaleController::class, 'cancelled'])
        ->middleware('permission:sales.view_cancelled')
        ->name('sales.cancelled');
    Route::get('/sales/product-search', [SaleController::class, 'productSearch'])
        ->middleware('permission:sales.create,sales.edit,sales.edit_approved,sales.proforma')
        ->name('sales.productSearch');
    Route::get('/products/{product}/sale-batches', [SaleController::class, 'productSaleBatches'])
        ->middleware('permission:sales.create,sales.edit,sales.edit_approved,sales.proforma')
        ->name('products.sale-batches');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])
        ->middleware('permission:sales.view,sales.view_pending,sales.view_approved,sales.view_cancelled')
        ->name('sales.show');
    Route::get('/sales/{sale}/print-pos', [SaleController::class, 'printPos'])
        ->middleware('permission:sales.view,sales.view_pending,sales.view_approved,sales.view_cancelled')
        ->name('sales.print.pos');
    Route::get('/sales/{sale}/print-a4', [SaleController::class, 'printA4'])
        ->middleware('permission:sales.view,sales.view_pending,sales.view_approved,sales.view_cancelled')
        ->name('sales.print.a4');
    Route::get('/sales/{sale}/edit', [SaleController::class, 'edit'])
        ->middleware('permission:sales.edit')
        ->name('sales.edit');
    Route::put('/sales/{sale}', [SaleController::class, 'update'])
        ->middleware('permission:sales.edit')
        ->name('sales.update');
    Route::get('/sales/{sale}/edit-proforma', [SaleController::class, 'editProforma'])
        ->middleware('permission:sales.proforma')
        ->name('sales.editProforma');
    Route::put('/sales/{sale}/update-proforma', [SaleController::class, 'updateProforma'])
        ->middleware('permission:sales.proforma')
        ->name('sales.updateProforma');
    Route::post('/sales/{sale}/approve', [SaleController::class, 'approve'])
        ->middleware('permission:sales.approve')
        ->name('sales.approve');
    Route::post('/sales/{sale}/cancel', [SaleController::class, 'cancel'])
        ->middleware('permission:sales.cancel')
        ->name('sales.cancel');
    Route::post('/sales/{sale}/restore', [SaleController::class, 'restore'])
        ->middleware('permission:sales.restore')
        ->name('sales.restore');
    Route::post('/sales/{sale}/convert-to-pending', [SaleController::class, 'convertProformaToPending'])
        ->middleware('permission:sales.create')
        ->name('sales.proforma.convert');
    Route::get('/sales/{sale}/edit-approved', [SaleController::class, 'editApproved'])
        ->middleware('permission:sales.edit_approved')
        ->name('sales.editApproved');
    Route::put('/sales/{sale}/update-approved', [SaleController::class, 'updateApproved'])
        ->middleware('permission:sales.edit_approved')
        ->name('sales.updateApproved');

    /*
    |--------------------------------------------------------------------------
    | CUSTOMERS & RECEIVABLES
    |--------------------------------------------------------------------------
    */
    Route::prefix('/customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])
            ->middleware('permission:customers.view')
            ->name('index');
        Route::get('/create', [CustomerController::class, 'create'])
            ->middleware('permission:customers.create')
            ->name('create');
        Route::post('/', [CustomerController::class, 'store'])
            ->middleware('permission:customers.create')
            ->name('store');
        Route::get('/receivables', [CustomerAccountController::class, 'receivables'])
            ->middleware('permission:customers.receivables')
            ->name('receivables');
        Route::get('/collections', [CustomerAccountController::class, 'collections'])
            ->middleware('permission:customers.collections.view')
            ->name('collections.index');
        Route::get('/invoices/{sale}/collect', [CustomerAccountController::class, 'createCollection'])
            ->middleware('permission:customers.collections.collect')
            ->name('collections.create');
        Route::post('/invoices/{sale}/collect', [CustomerAccountController::class, 'storeCollection'])
            ->middleware('permission:customers.collections.collect')
            ->name('collections.store');
        Route::get('/payments/{payment}/reverse', [CustomerAccountController::class, 'createReversal'])
            ->middleware('permission:customers.collections.reverse')
            ->name('collections.reverse.create');
        Route::post('/payments/{payment}/reverse', [CustomerAccountController::class, 'storeReversal'])
            ->middleware('permission:customers.collections.reverse')
            ->name('collections.reverse.store');
        Route::get('/{customer}', [CustomerController::class, 'show'])
            ->middleware('permission:customers.view')
            ->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])
            ->middleware('permission:customers.edit')
            ->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])
            ->middleware('permission:customers.edit')
            ->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])
            ->middleware('permission:customers.delete')
            ->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | SUPPLIERS
    |--------------------------------------------------------------------------
    */
    Route::prefix('/suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])
            ->middleware('permission:suppliers.view')
            ->name('index');
        Route::get('/statement', [SupplierController::class, 'statement'])
            ->middleware('permission:suppliers.view')
            ->name('statement');
        Route::get('/payables', [SupplierAccountController::class, 'payables'])
            ->middleware('permission:suppliers.payables')
            ->name('payables');
        Route::get('/payments', [SupplierAccountController::class, 'payments'])
            ->middleware('permission:suppliers.payments.view')
            ->name('payments.index');
        Route::get('/create', [SupplierController::class, 'create'])
            ->middleware('permission:suppliers.create')
            ->name('create');
        Route::post('/', [SupplierController::class, 'store'])
            ->middleware('permission:suppliers.create')
            ->name('store');
        Route::get('/invoices/{purchase}/pay', [SupplierAccountController::class, 'createPayment'])
            ->middleware('permission:suppliers.payments.pay')
            ->name('payments.create');
        Route::post('/invoices/{purchase}/pay', [SupplierAccountController::class, 'storePayment'])
            ->middleware('permission:suppliers.payments.pay')
            ->name('payments.store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])
            ->middleware('permission:suppliers.view')
            ->name('show');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])
            ->middleware('permission:suppliers.edit')
            ->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])
            ->middleware('permission:suppliers.edit')
            ->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])
            ->middleware('permission:suppliers.delete')
            ->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | STOCK
    |--------------------------------------------------------------------------
    */
    Route::prefix('/stock')->name('stock.')->group(function () {
        Route::get('/', [StockController::class, 'index'])
            ->middleware('permission:stock.view,stock.adjust')
            ->name('index');
        Route::get('/batches/{batch}/adjust', [StockController::class, 'createAdjustment'])
            ->middleware('permission:stock.adjust')
            ->name('adjust.create');
        Route::post('/batches/{batch}/adjust', [StockController::class, 'storeAdjustment'])
            ->middleware('permission:stock.adjust')
            ->name('adjust.store');
    });

    Route::prefix('/insurance')->name('insurance.')->group(function () {
        Route::get('/claims', [InsuranceController::class, 'claims'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('claims.index');
        Route::get('/claims/{sale}', [InsuranceController::class, 'showClaim'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('claims.show');
        Route::put('/claims/{sale}/status', [InsuranceController::class, 'updateClaimStatus'])
            ->middleware('permission:insurance.manage')
            ->name('claims.status.update');
        Route::post('/claims/{sale}/adjustments', [InsuranceController::class, 'storeAdjustment'])
            ->middleware('permission:insurance.manage')
            ->name('claims.adjustments.store');
        Route::get('/claims/{sale}/payments/create', [InsuranceController::class, 'createPayment'])
            ->middleware('permission:insurance.manage')
            ->name('payments.create');
        Route::post('/claims/{sale}/payments', [InsuranceController::class, 'storePayment'])
            ->middleware('permission:insurance.manage')
            ->name('payments.store');
        Route::get('/payments/{payment}/reverse', [InsuranceController::class, 'createPaymentReversal'])
            ->middleware('permission:insurance.manage')
            ->name('payments.reverse.create');
        Route::post('/payments/{payment}/reverse', [InsuranceController::class, 'storePaymentReversal'])
            ->middleware('permission:insurance.manage')
            ->name('payments.reverse.store');
        Route::get('/batches', [InsuranceController::class, 'batches'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('batches.index');
        Route::post('/batches', [InsuranceController::class, 'storeBatch'])
            ->middleware('permission:insurance.manage')
            ->name('batches.store');
        Route::get('/batches/{batch}', [InsuranceController::class, 'showBatch'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('batches.show');
        Route::put('/batches/{batch}/status', [InsuranceController::class, 'updateBatchStatus'])
            ->middleware('permission:insurance.manage')
            ->name('batches.status.update');
        Route::get('/statements', [InsuranceController::class, 'statements'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('statements.index');
        Route::get('/insurers', [InsuranceController::class, 'insurers'])
            ->middleware('permission:insurance.view,insurance.manage')
            ->name('insurers.index');
        Route::post('/insurers', [InsuranceController::class, 'storeInsurer'])
            ->middleware('permission:insurance.manage')
            ->name('insurers.store');
        Route::put('/insurers/{insurer}', [InsuranceController::class, 'updateInsurer'])
            ->middleware('permission:insurance.manage')
            ->name('insurers.update');
    });

    Route::prefix('/cash-drawer')->name('cash-drawer.')->group(function () {
        Route::get('/', [CashDrawerController::class, 'index'])
            ->middleware('permission:cash_drawer.view,cash_drawer.manage')
            ->name('index');
        Route::put('/opening-balance', [CashDrawerController::class, 'updateOpening'])
            ->middleware('permission:cash_drawer.manage')
            ->name('opening.update');
        Route::post('/shifts/open', [CashDrawerController::class, 'openShift'])
            ->middleware('permission:cash_drawer.manage')
            ->name('shifts.open');
        Route::post('/shifts/{shift}/close', [CashDrawerController::class, 'closeShift'])
            ->middleware('permission:cash_drawer.manage')
            ->name('shifts.close');
        Route::post('/draws', [CashDrawerController::class, 'storeDraw'])
            ->middleware('permission:cash_drawer.manage')
            ->name('draws.store');
        Route::post('/day-close', [CashDrawerController::class, 'closeDay'])
            ->middleware('permission:cash_drawer.manage')
            ->name('day.close');
        Route::post('/day-close/reopen', [CashDrawerController::class, 'reopenDay'])
            ->middleware('permission:cash_drawer.manage')
            ->name('day.reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTING
    |--------------------------------------------------------------------------
    */
    Route::prefix('/accounting')->name('accounting.')->group(function () {
        Route::get('/', [AccountingController::class, 'index'])
            ->middleware('permission:accounting.view')
            ->name('index');
        Route::get('/chart-of-accounts', [AccountingController::class, 'chartOfAccounts'])
            ->middleware('permission:accounting.chart')
            ->name('chart');
        Route::get('/chart-of-accounts/print', [AccountingController::class, 'printChartOfAccounts'])
            ->middleware('permission:accounting.chart')
            ->name('chart.print');
        Route::get('/chart-of-accounts/download', [AccountingController::class, 'downloadChartOfAccounts'])
            ->middleware('permission:accounting.chart')
            ->name('chart.download');
        Route::get('/general-ledger', [AccountingController::class, 'generalLedger'])
            ->middleware('permission:accounting.general_ledger')
            ->name('general-ledger');
        Route::get('/general-ledger/print', [AccountingController::class, 'printGeneralLedger'])
            ->middleware('permission:accounting.general_ledger')
            ->name('general-ledger.print');
        Route::get('/general-ledger/download', [AccountingController::class, 'downloadGeneralLedger'])
            ->middleware('permission:accounting.general_ledger')
            ->name('general-ledger.download');
        Route::get('/trial-balance', [AccountingController::class, 'trialBalance'])
            ->middleware('permission:accounting.trial_balance')
            ->name('trial-balance');
        Route::get('/trial-balance/print', [AccountingController::class, 'printTrialBalance'])
            ->middleware('permission:accounting.trial_balance')
            ->name('trial-balance.print');
        Route::get('/trial-balance/download', [AccountingController::class, 'downloadTrialBalance'])
            ->middleware('permission:accounting.trial_balance')
            ->name('trial-balance.download');
        Route::get('/journals', [AccountingController::class, 'journals'])
            ->middleware('permission:accounting.journals')
            ->name('journals');
        Route::get('/journals/print', [AccountingController::class, 'printJournals'])
            ->middleware('permission:accounting.journals')
            ->name('journals.print');
        Route::get('/journals/download', [AccountingController::class, 'downloadJournals'])
            ->middleware('permission:accounting.journals')
            ->name('journals.download');
        Route::get('/payment-vouchers', [AccountingController::class, 'paymentVouchers'])
            ->middleware('permission:accounting.vouchers')
            ->name('vouchers');
        Route::get('/payment-vouchers/print', [AccountingController::class, 'printPaymentVouchers'])
            ->middleware('permission:accounting.vouchers')
            ->name('vouchers.print');
        Route::get('/payment-vouchers/download', [AccountingController::class, 'downloadPaymentVouchers'])
            ->middleware('permission:accounting.vouchers')
            ->name('vouchers.download');
        Route::get('/profit-loss', [AccountingController::class, 'profitAndLoss'])
            ->middleware('permission:accounting.profit_loss')
            ->name('profit-loss');
        Route::get('/profit-loss/print', [AccountingController::class, 'printProfitAndLoss'])
            ->middleware('permission:accounting.profit_loss')
            ->name('profit-loss.print');
        Route::get('/profit-loss/download', [AccountingController::class, 'downloadProfitAndLoss'])
            ->middleware('permission:accounting.profit_loss')
            ->name('profit-loss.download');
        Route::get('/balance-sheet', [AccountingController::class, 'balanceSheet'])
            ->middleware('permission:accounting.balance_sheet')
            ->name('balance-sheet');
        Route::get('/balance-sheet/print', [AccountingController::class, 'printBalanceSheet'])
            ->middleware('permission:accounting.balance_sheet')
            ->name('balance-sheet.print');
        Route::get('/balance-sheet/download', [AccountingController::class, 'downloadBalanceSheet'])
            ->middleware('permission:accounting.balance_sheet')
            ->name('balance-sheet.download');
        Route::get('/expenses', [AccountingController::class, 'expensesIndex'])
            ->middleware('permission:accounting.expenses.view')
            ->name('expenses.index');
        Route::get('/expenses/print', [AccountingController::class, 'printExpenses'])
            ->middleware('permission:accounting.expenses.view')
            ->name('expenses.print');
        Route::get('/expenses/download', [AccountingController::class, 'downloadExpenses'])
            ->middleware('permission:accounting.expenses.view')
            ->name('expenses.download');
        Route::get('/expenses/create', [AccountingController::class, 'createExpense'])
            ->middleware('permission:accounting.expenses.manage')
            ->name('expenses.create');
        Route::post('/expenses', [AccountingController::class, 'storeExpense'])
            ->middleware('permission:accounting.expenses.manage')
            ->name('expenses.store');
        Route::get('/fixed-assets', [AccountingController::class, 'fixedAssetsIndex'])
            ->middleware('permission:accounting.fixed_assets.view')
            ->name('fixed-assets.index');
        Route::get('/fixed-assets/print', [AccountingController::class, 'printFixedAssets'])
            ->middleware('permission:accounting.fixed_assets.view')
            ->name('fixed-assets.print');
        Route::get('/fixed-assets/download', [AccountingController::class, 'downloadFixedAssets'])
            ->middleware('permission:accounting.fixed_assets.view')
            ->name('fixed-assets.download');
        Route::get('/fixed-assets/create', [AccountingController::class, 'createFixedAsset'])
            ->middleware('permission:accounting.fixed_assets.manage')
            ->name('fixed-assets.create');
        Route::post('/fixed-assets', [AccountingController::class, 'storeFixedAsset'])
            ->middleware('permission:accounting.fixed_assets.manage')
            ->name('fixed-assets.store');
    });

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATION
    |--------------------------------------------------------------------------
    */
    Route::prefix('/admin')->name('admin.')->group(function () {
        Route::get('/platform', [SuperAdminController::class, 'index'])
            ->middleware('super.admin')
            ->name('platform.index');
        Route::put('/platform', [SuperAdminController::class, 'update'])
            ->middleware('super.admin')
            ->name('platform.update');
        Route::delete('/platform', [SuperAdminController::class, 'clear'])
            ->middleware('super.admin')
            ->name('platform.clear');
        Route::post('/platform/sandbox', [SuperAdminController::class, 'useSandbox'])
            ->middleware('super.admin')
            ->name('platform.sandbox');
        Route::put('/platform/support', [SuperAdminController::class, 'updateSupport'])
            ->middleware('super.admin')
            ->name('platform.support.update');
        Route::get('/platform/backups', [PlatformBackupController::class, 'index'])
            ->middleware('super.admin')
            ->name('platform.backups.index');
        Route::post('/platform/backups', [PlatformBackupController::class, 'store'])
            ->middleware('super.admin')
            ->name('platform.backups.store');
        Route::get('/platform/backups/{backup}', [PlatformBackupController::class, 'show'])
            ->middleware('super.admin')
            ->name('platform.backups.show');
        Route::get('/platform/backups/{backup}/download', [PlatformBackupController::class, 'download'])
            ->middleware('super.admin')
            ->name('platform.backups.download');
        Route::put('/platform/backups/{backup}/restore', [PlatformBackupController::class, 'restore'])
            ->middleware('super.admin')
            ->name('platform.backups.restore');
        Route::get('/platform/clients', [PlatformClientController::class, 'index'])
            ->middleware('super.admin')
            ->name('platform.clients.index');
        Route::get('/platform/clients/create', [PlatformClientController::class, 'create'])
            ->middleware('super.admin')
            ->name('platform.clients.create');
        Route::post('/platform/clients', [PlatformClientController::class, 'store'])
            ->middleware('super.admin')
            ->name('platform.clients.store');
        Route::get('/platform/clients/{client}/edit', [PlatformClientController::class, 'edit'])
            ->middleware('super.admin')
            ->name('platform.clients.edit');
        Route::put('/platform/clients/{client}', [PlatformClientController::class, 'update'])
            ->middleware('super.admin')
            ->name('platform.clients.update');
        Route::put('/platform/clients/{client}/subscription', [PlatformClientController::class, 'updateSubscription'])
            ->middleware('super.admin')
            ->name('platform.clients.subscription.update');
        Route::get('/platform/clients/{client}/branches', [PlatformClientController::class, 'branches'])
            ->middleware('super.admin')
            ->name('platform.branches.index');
        Route::get('/platform/clients/{client}/branches/create', [PlatformClientController::class, 'createBranch'])
            ->middleware('super.admin')
            ->name('platform.branches.create');
        Route::post('/platform/clients/{client}/branches', [PlatformClientController::class, 'storeBranch'])
            ->middleware('super.admin')
            ->name('platform.branches.store');
        Route::get('/platform/clients/{client}/branches/{branch}/edit', [PlatformClientController::class, 'editBranch'])
            ->middleware('super.admin')
            ->name('platform.branches.edit');
        Route::put('/platform/clients/{client}/branches/{branch}', [PlatformClientController::class, 'updateBranch'])
            ->middleware('super.admin')
            ->name('platform.branches.update');

        Route::get('/users', [UserManagementController::class, 'index'])
            ->middleware('permission:users.manage')
            ->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])
            ->middleware('permission:users.manage')
            ->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])
            ->middleware('permission:users.manage')
            ->name('users.store');
        Route::get('/users/{managedUser}/edit', [UserManagementController::class, 'edit'])
            ->middleware('permission:users.manage')
            ->name('users.edit');
        Route::put('/users/{managedUser}', [UserManagementController::class, 'update'])
            ->middleware('permission:users.manage')
            ->name('users.update');
        Route::patch('/users/{managedUser}/status', [UserManagementController::class, 'toggleStatus'])
            ->middleware('permission:users.manage')
            ->name('users.status');

        Route::get('/roles', [RoleManagementController::class, 'index'])
            ->middleware('permission:roles.manage')
            ->name('roles.index');
        Route::get('/roles/create', [RoleManagementController::class, 'create'])
            ->middleware('permission:roles.manage')
            ->name('roles.create');
        Route::post('/roles', [RoleManagementController::class, 'store'])
            ->middleware('permission:roles.manage')
            ->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleManagementController::class, 'edit'])
            ->middleware('permission:roles.manage')
            ->name('roles.edit');
        Route::put('/roles/{role}', [RoleManagementController::class, 'update'])
            ->middleware('permission:roles.manage')
            ->name('roles.update');
        Route::get('/audit-trail', [AuditTrailController::class, 'index'])
            ->middleware('permission:audit.view')
            ->name('audit.index');
        Route::get('/imports', [DataImportController::class, 'index'])
            ->middleware('permission:data_import.manage')
            ->name('imports.index');
        Route::get('/imports/templates/{dataset}', [DataImportController::class, 'downloadTemplate'])
            ->middleware('permission:data_import.manage')
            ->name('imports.template');
        Route::post('/imports/preview', [DataImportController::class, 'preview'])
            ->middleware('permission:data_import.manage')
            ->name('imports.preview');
        Route::post('/imports', [DataImportController::class, 'store'])
            ->middleware('permission:data_import.manage')
            ->name('imports.store');
        Route::delete('/imports', [DataImportController::class, 'clear'])
            ->middleware('permission:data_import.manage')
            ->name('imports.clear');
    });

    Route::get('/reports', [ReportsController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('/reports/print', [ReportsController::class, 'print'])
        ->middleware('permission:reports.view')
        ->name('reports.print');
    Route::get('/reports/download', [ReportsController::class, 'download'])
        ->middleware('permission:reports.view')
        ->name('reports.download');

    Route::get('/settings', [SettingsController::class, 'index'])
        ->middleware('permission:settings.view,settings.manage')
        ->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])
        ->middleware('permission:settings.manage')
        ->name('settings.update');
    Route::post('/settings/efris/process', [SettingsController::class, 'processEfris'])
        ->middleware('permission:settings.manage')
        ->name('settings.efris.process');
});
