<?php

use App\Modules\Payments\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/payments/create', [PaymentController::class, 'create'])
    ->middleware(['can:payments.create', 'can:payments.receive'])
    ->name('payments.create');

Route::get('/payments/customers/{customer}/jobs', [PaymentController::class, 'jobs'])
    ->middleware(['can:payments.create', 'can:payments.receive'])
    ->name('payments.jobs');

Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware(['can:payments.create', 'can:payments.receive'])
    ->name('payments.store');

Route::get('/payments', [PaymentController::class, 'index'])
    ->middleware('can:payments.view')
    ->name('payments.index');

Route::get('/payments/{payment}/print', [PaymentController::class, 'print'])
    ->middleware('can:print,payment')
    ->name('payments.print');

Route::get('/payments/{payment}/duplicate', [PaymentController::class, 'duplicate'])
    ->middleware('can:print,payment')
    ->name('payments.duplicate');

Route::get('/payments/{payment}/pdf', [PaymentController::class, 'pdf'])
    ->middleware('can:export,payment')
    ->name('payments.pdf');

Route::post('/payments/{payment}/reverse', [PaymentController::class, 'reverse'])
    ->middleware('can:reverse,payment')
    ->name('payments.reverse');

Route::get('/payments/{payment}', [PaymentController::class, 'show'])
    ->middleware('can:view,payment')
    ->name('payments.show');
