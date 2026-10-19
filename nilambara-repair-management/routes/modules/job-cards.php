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
