<?php

use App\Modules\Quotations\Controllers\QuotationController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:quotations.create')->group(function (): void {
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::get('/quotations/repair-jobs/search', [QuotationController::class, 'repairJobs'])->name('quotations.repair-jobs');
    Route::get('/quotations/spare-parts/search', [QuotationController::class, 'spareParts'])->name('quotations.spare-parts');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
});

Route::middleware('can:quotations.view')->group(function (): void {
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])
        ->middleware('can:print,quotation')
        ->name('quotations.print');
    Route::get('/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])
        ->middleware('can:print,quotation')
        ->name('quotations.duplicate');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])
        ->middleware('can:print,quotation')
        ->name('quotations.pdf');
});

Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])
    ->middleware('can:update,quotation')
    ->name('quotations.update');
Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])
    ->middleware('can:update,quotation')
    ->name('quotations.edit');
Route::post('/quotations/{quotation}/issue', [QuotationController::class, 'issue'])
    ->middleware('can:issue,quotation')
    ->name('quotations.issue');
Route::post('/quotations/{quotation}/decision', [QuotationController::class, 'decision'])
    ->middleware('can:recordDecision,quotation')
    ->name('quotations.decision');
Route::post('/quotations/{quotation}/revisions', [QuotationController::class, 'revise'])
    ->middleware('can:revise,quotation')
    ->name('quotations.revise');
Route::post('/quotations/{quotation}/cancel', [QuotationController::class, 'cancel'])
    ->middleware('can:cancel,quotation')
    ->name('quotations.cancel');
