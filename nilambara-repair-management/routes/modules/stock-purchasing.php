<?php

use App\Modules\Inventory\Controllers\InventoryController;
use App\Modules\Purchases\Controllers\PurchaseController;
use App\Modules\Suppliers\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::get('/inventory/create', [InventoryController::class, 'form'])->name('inventory.create');
Route::get('/inventory/import', [InventoryController::class, 'importForm'])->middleware('can:inventory.create')->name('inventory.import');
Route::get('/inventory/import/template', [InventoryController::class, 'importTemplate'])->middleware('can:inventory.create')->name('inventory.import.template');
Route::post('/inventory/import', [InventoryController::class, 'import'])->middleware(['can:inventory.create', 'throttle:20,1'])->name('inventory.import.store');
Route::post('/inventory', [InventoryController::class, 'save'])->middleware('can:inventory.create')->name('inventory.store');
Route::post('/inventory/references', [InventoryController::class, 'reference'])->name('inventory.references');
Route::get('/inventory/{part}/edit', [InventoryController::class, 'form'])->name('inventory.edit');
Route::get('/inventory/{part}', [InventoryController::class, 'show'])->name('inventory.show');
Route::put('/inventory/{part}', [InventoryController::class, 'save'])->middleware('can:inventory.update')->name('inventory.update');
Route::post('/inventory/{part}/{operation}', [InventoryController::class, 'operation'])->whereIn('operation', ['adjust', 'count'])->name('inventory.operation');
Route::get('/stock-issues', [InventoryController::class, 'issues'])->name('stock-issues.index');
Route::post('/stock-issues', [InventoryController::class, 'requestPart'])->middleware('can:jobs.repair')->name('stock-issues.store');
Route::post('/stock-issues/{usage}/issue', [InventoryController::class, 'issue'])->middleware('can:inventory.issue')->name('stock-issues.issue');
Route::post('/stock-issues/{usage}/cancel', [InventoryController::class, 'cancelUsage'])->middleware('can:inventory.issue')->name('stock-issues.cancel');

Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
Route::get('/suppliers/create', [SupplierController::class, 'form'])->name('suppliers.create');
Route::post('/suppliers', [SupplierController::class, 'save'])->middleware('can:suppliers.create')->name('suppliers.store');
Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'form'])->name('suppliers.edit');
Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
Route::put('/suppliers/{supplier}', [SupplierController::class, 'save'])->middleware('can:suppliers.update')->name('suppliers.update');
Route::post('/suppliers/{supplier}/opening-payments', [SupplierController::class, 'payOpening'])->middleware('can:purchases.approve')->name('suppliers.pay-opening');

Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create');
Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('can:purchases.create')->name('purchases.store');
Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
Route::get('/purchases/{purchase}/document/{format}', [PurchaseController::class, 'document'])->whereIn('format', ['html', 'pdf'])->middleware('can:purchases.view')->name('purchases.document');
Route::get('/goods-received-notes/{grn}/document/{format}', [PurchaseController::class, 'grnDocument'])->whereIn('format', ['html', 'pdf'])->middleware('can:purchases.view')->name('grns.document');
Route::post('/purchases/{purchase}/finalize', [PurchaseController::class, 'finalize'])->middleware('can:purchases.approve')->name('purchases.finalize');
Route::post('/purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('can:purchases.receive')->name('purchases.receive');
Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'pay'])->middleware('can:purchases.approve')->name('purchases.pay');
Route::post('/purchases/{purchase}/returns', [PurchaseController::class, 'returnStock'])->middleware(['can:purchases.receive', 'can:inventory.adjust'])->name('purchases.return');
Route::post('/supplier-payments/{payment}/reverse', [PurchaseController::class, 'reverse'])->middleware('can:purchases.approve')->name('supplier-payments.reverse');
