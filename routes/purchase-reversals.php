<?php

use App\Http\Controllers\PurchaseReversalController;
use Illuminate\Support\Facades\Route;

Route::get('/purchases/{purchase}/reversal', [PurchaseReversalController::class, 'show'])
    ->middleware(['permission:purchases.correct_items', 'permission:suppliers.payments.pay'])->name('purchases.reversal');
Route::post('/purchases/{purchase}/reversal', [PurchaseReversalController::class, 'store'])
    ->middleware(['permission:purchases.correct_items', 'permission:suppliers.payments.pay'])->name('purchases.reversal.store');
