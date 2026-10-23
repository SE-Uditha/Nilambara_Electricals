<?php

use App\Modules\RepairJobs\Controllers\JobBillingController;
use App\Modules\RepairJobs\Controllers\RepairJobController;
use App\Modules\RepairReceipts\Controllers\RepairReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/repair-jobs/{job}/billing', [JobBillingController::class, 'edit'])->middleware('can:invoices.create')->name('repair-jobs.billing');
Route::post('/repair-jobs/{job}/billing', [JobBillingController::class, 'update'])->middleware('can:invoices.create')->name('repair-jobs.billing.update');

Route::middleware(['can:jobs.create', 'can:receipts.create'])->group(function (): void {
    Route::get('/repair-jobs/create', [RepairReceiptController::class, 'create'])->name('repair-jobs.create');
    Route::post('/repair-jobs', [RepairReceiptController::class, 'store'])->name('repair-jobs.store');
});
Route::get('/repair-jobs/{job}/receipt/print', [RepairJobController::class, 'printReceipt'])->middleware(['can:jobs.view', 'can:receipts.print'])->name('repair-jobs.receipt.print');
Route::get('/repair-jobs/{job}/receipt/duplicate', [RepairJobController::class, 'duplicateReceipt'])->middleware(['can:jobs.view', 'can:receipts.reprint'])->name('repair-jobs.receipt.duplicate');
Route::get('/repair-jobs/{job}/receipt/pdf', [RepairJobController::class, 'receiptPdf'])->middleware(['can:jobs.view', 'can:receipts.print'])->name('repair-jobs.receipt.pdf');

Route::post('/repair-jobs/{job}/labour-charge', [RepairJobController::class, 'setLabourCharge'])->middleware('can:invoices.create')->name('repair-jobs.labour-charge');

Route::middleware('can:jobs.view')->group(function (): void {
    Route::get('/repair-jobs', [RepairJobController::class, 'index'])->name('repair-jobs.index');
    Route::get('/repair-jobs/status', [RepairJobController::class, 'statusSearch'])->name('repair-jobs.status-search');
    Route::get('/my-assigned-jobs', [RepairJobController::class, 'myAssigned'])->name('repair-jobs.assigned');
    Route::get('/repair-jobs/{job}', [RepairJobController::class, 'show'])->name('repair-jobs.show');
});

Route::middleware('can:jobs.view')->group(function (): void {
    Route::get('/repair-jobs/{job}/request-edit', [RepairJobController::class, 'requestEdit'])->name('repair-jobs.request-edit');
    Route::post('/repair-jobs/{job}/request-edit', [RepairJobController::class, 'storeEditRequest'])->name('repair-jobs.request-edit.store');
});

Route::middleware('can:jobs.update')->group(function (): void {
    Route::get('/repair-jobs/{job}/edit', [RepairJobController::class, 'edit'])->name('repair-jobs.edit');
    Route::put('/repair-jobs/{job}', [RepairJobController::class, 'update'])->name('repair-jobs.update');
});

Route::middleware('can:jobs.assign')->group(function (): void {
    Route::get('/repair-jobs/{job}/assign', [RepairJobController::class, 'assignForm'])->name('repair-jobs.assign-form');
    Route::post('/repair-jobs/{job}/assign', [RepairJobController::class, 'assign'])->name('repair-jobs.assign');
    Route::post('/repair-jobs/{job}/technicians', [RepairJobController::class, 'storeTechnician'])->name('repair-jobs.technicians.store');
    Route::put('/repair-jobs/{job}/technicians/{technician}', [RepairJobController::class, 'updateTechnician'])->name('repair-jobs.technicians.update');
    Route::delete('/repair-jobs/{job}/technicians/{technician}', [RepairJobController::class, 'destroyTechnician'])->name('repair-jobs.technicians.destroy');
});

Route::post('/repair-jobs/{job}/inspection', [RepairJobController::class, 'inspect'])
    ->middleware('can:jobs.inspect')
    ->name('repair-jobs.inspect');

Route::post('/repair-jobs/{job}/repair', [RepairJobController::class, 'saveRepair'])
    ->middleware('can:jobs.repair')
    ->name('repair-jobs.save-repair');

Route::post('/repair-jobs/{job}/status', [RepairJobController::class, 'updateStatus'])
    ->middleware('can:jobs.update-status')
    ->name('repair-jobs.update-status');
