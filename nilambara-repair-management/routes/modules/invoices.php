<?php

use App\Modules\Invoices\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/invoices/create', [InvoiceController::class, 'create'])
    ->middleware('can:invoices.create')
    ->name('invoices.create');

Route::get('/invoices/customers/{customer}/jobs', [InvoiceController::class, 'eligibleJobs'])
    ->middleware('can:invoices.create')
    ->name('invoices.jobs');

Route::post('/invoices', [InvoiceController::class, 'store'])
    ->middleware('can:invoices.create')
    ->name('invoices.store');

Route::get('/invoices', [InvoiceController::class, 'index'])
    ->middleware('can:invoices.view')
    ->name('invoices.index');

Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
    ->middleware('can:invoices.view')
    ->name('invoices.show');

Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])
    ->middleware('can:print,invoice')
    ->name('invoices.print');

Route::get('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])
    ->middleware('can:print,invoice')
    ->name('invoices.duplicate');

Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
    ->middleware('can:export,invoice')
    ->name('invoices.pdf');

Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])
    ->middleware('can:updateDraft,invoice')
    ->name('invoices.edit');

Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])
    ->middleware('can:updateDraft,invoice')
    ->name('invoices.update');

Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])
    ->middleware('can:issue,invoice')
    ->name('invoices.issue');

Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
    ->middleware('can:cancel,invoice')
    ->name('invoices.cancel');
