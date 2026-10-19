<?php

use App\Modules\CreditManagement\Controllers\CreditInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/credit-invoices', [CreditInvoiceController::class, 'index'])
    ->middleware('can:credit.view')
    ->name('credit-invoices.index');

Route::get('/credit-invoices/{invoice}', [CreditInvoiceController::class, 'show'])
    ->middleware('can:credit.view')
    ->name('credit-invoices.show');

Route::get('/credit-invoices/{invoice}/collect', [CreditInvoiceController::class, 'collect'])
    ->middleware('can:credit.collect')
    ->name('credit-invoices.collect');
