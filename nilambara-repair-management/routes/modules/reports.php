<?php

use App\Modules\Reports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:reports.view')->group(function (): void {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}/export/{format}', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
});
