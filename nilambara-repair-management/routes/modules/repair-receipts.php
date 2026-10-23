<?php

use App\Modules\RepairReceipts\Controllers\RepairReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:receipts.view')->group(function (): void {
    Route::get('/repair-receipts', [RepairReceiptController::class, 'index'])->name('repair-receipts.index');
});

Route::middleware(['can:jobs.create', 'can:receipts.create'])->group(function (): void {
    Route::get('/repair-receipts/create', [RepairReceiptController::class, 'create'])->name('repair-receipts.create');
    Route::post('/repair-receipts', [RepairReceiptController::class, 'store'])->name('repair-receipts.store');
});

Route::get('/repair-receipts/{receipt}', [RepairReceiptController::class, 'show'])
    ->middleware('can:receipts.view')
    ->name('repair-receipts.show');

Route::get('/repair-receipts/{receipt}/print', [RepairReceiptController::class, 'print'])
    ->middleware('can:receipts.print')
    ->name('repair-receipts.print');

Route::get('/repair-receipts/{receipt}/duplicate', [RepairReceiptController::class, 'duplicate'])
    ->middleware('can:receipts.reprint')
    ->name('repair-receipts.duplicate');

Route::get('/repair-receipts/{receipt}/pdf', [RepairReceiptController::class, 'pdf'])->middleware('can:receipts.print')->name('repair-receipts.pdf');
