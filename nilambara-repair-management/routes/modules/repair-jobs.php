<?php

use App\Modules\RepairJobs\Controllers\RepairJobController;
use Illuminate\Support\Facades\Route;

Route::post('/repair-jobs/{job}/labour-charge', [RepairJobController::class, 'setLabourCharge'])->middleware('can:invoices.create')->name('repair-jobs.labour-charge');

Route::middleware('can:jobs.view')->group(function (): void {
    Route::get('/repair-jobs', [RepairJobController::class, 'index'])->name('repair-jobs.index');
    Route::get('/repair-jobs/status', [RepairJobController::class, 'statusSearch'])->name('repair-jobs.status-search');
    Route::get('/my-assigned-jobs', [RepairJobController::class, 'myAssigned'])->name('repair-jobs.assigned');
    Route::get('/repair-jobs/{job}', [RepairJobController::class, 'show'])->name('repair-jobs.show');
});

Route::middleware('can:jobs.update')->group(function (): void {
    Route::get('/repair-jobs/{job}/edit', [RepairJobController::class, 'edit'])->name('repair-jobs.edit');
    Route::put('/repair-jobs/{job}', [RepairJobController::class, 'update'])->name('repair-jobs.update');
});

Route::middleware('can:jobs.assign')->group(function (): void {
    Route::get('/repair-jobs/{job}/assign', [RepairJobController::class, 'assignForm'])->name('repair-jobs.assign-form');
    Route::post('/repair-jobs/{job}/assign', [RepairJobController::class, 'assign'])->name('repair-jobs.assign');
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
