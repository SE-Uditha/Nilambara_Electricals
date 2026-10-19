<?php

use App\Modules\POS\Controllers\POSController;
use App\Modules\POS\Controllers\POSReturnController;
use Illuminate\Support\Facades\Route;

Route::get('/pos', [POSController::class, 'index'])->middleware('can:pos.view')->name('pos.index');
Route::post('/pos/cart/add', [POSController::class, 'addProduct'])->middleware('can:pos.sale')->name('pos.cart.add');
Route::put('/pos/cart/{part}', [POSController::class, 'updateQuantity'])->middleware('can:pos.sale')->name('pos.cart.update');
Route::delete('/pos/cart/{part}', [POSController::class, 'removeProduct'])->middleware('can:pos.sale')->name('pos.cart.remove');
Route::delete('/pos/cart', [POSController::class, 'clearCart'])->middleware('can:pos.sale')->name('pos.cart.clear');
Route::post('/pos/checkout', [POSController::class, 'checkout'])->middleware('can:pos.sale')->name('pos.checkout');

Route::get('/pos/sales', [POSController::class, 'sales'])->middleware('can:pos.sales.view')->name('pos.sales.index');
Route::get('/pos/returns', [POSController::class, 'returns'])->middleware('can:pos.return')->name('pos.returns.index');
Route::post('/pos/returns', [POSReturnController::class, 'store'])->middleware('can:pos.return')->name('pos.returns.store');
Route::get('/pos/reports', [POSController::class, 'reports'])->middleware('can:pos.reports.view')->name('pos.reports');

Route::get('/pos/sales/{sale}', [POSController::class, 'show'])->middleware('can:pos.sales.view')->name('pos.sales.show');
Route::post('/pos/sales/{sale}/verify', [POSController::class, 'verify'])->middleware('can:pos.payment.verify')->name('pos.sales.verify');
Route::post('/pos/sales/{sale}/cancel', [POSController::class, 'cancel'])->middleware('can:pos.sale.cancel')->name('pos.sales.cancel');
Route::get('/pos/sales/{sale}/print', [POSController::class, 'print'])->middleware('can:pos.receipt.print')->name('pos.sales.print');
Route::get('/pos/sales/{sale}/reprint', [POSController::class, 'reprint'])->middleware('can:pos.receipt.print')->name('pos.sales.reprint');
Route::get('/pos/sales/{sale}/pdf', [POSController::class, 'pdf'])->middleware('can:pos.receipt.print')->name('pos.sales.pdf');
