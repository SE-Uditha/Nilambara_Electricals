<?php

use App\Modules\JobCards\Controllers\JobCardController;
use Illuminate\Support\Facades\Route;

Route::get('/job-cards', [JobCardController::class, 'index'])
    ->middleware('can:job-cards.view')
    ->name('job-cards.index');

Route::get('/repair-jobs/{job}/job-card', [JobCardController::class, 'print'])
    ->middleware('can:job-cards.print')
    ->name('job-cards.print');

Route::get('/repair-jobs/{job}/job-card/duplicate', [JobCardController::class, 'duplicate'])
    ->middleware('can:job-cards.print')
    ->name('job-cards.duplicate');

Route::get('/repair-jobs/{job}/job-card/pdf', [JobCardController::class, 'pdf'])->middleware('can:job-cards.print')->name('job-cards.pdf');

Route::post('/repair-jobs/{job}/job-card/confirm-print', [JobCardController::class, 'confirmPrint'])
    ->middleware(['can:job-cards.print', 'throttle:30,1'])
    ->name('job-cards.confirm-print');
