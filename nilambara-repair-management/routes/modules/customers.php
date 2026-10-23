<?php

use App\Modules\Customers\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerController::class, 'index'])
    ->middleware('can:customers.view')
    ->name('customers.index');

Route::get('/customers/search', [CustomerController::class, 'search'])
    ->middleware('can:customers.view')
    ->name('customers.search');

Route::get('/customers/create', [CustomerController::class, 'create'])
    ->middleware('can:customers.create')
    ->name('customers.create');

Route::post('/customers', [CustomerController::class, 'store'])
    ->middleware('can:customers.create')
    ->name('customers.store');

Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])
    ->middleware('can:customers.statement')
    ->name('customers.statement');

Route::get('/customers/{customer}/statement/print', [CustomerController::class, 'statementPrint'])
    ->middleware('can:customers.statement')
    ->name('customers.statement.print');

Route::get('/customers/{customer}/statement/pdf', [CustomerController::class, 'statementPdf'])
    ->middleware('can:customers.statement')
    ->name('customers.statement.pdf');

Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
    ->middleware('can:customers.update')
    ->name('customers.edit');

Route::get('/customers/{customer}/request-edit', [CustomerController::class, 'requestEdit'])
    ->middleware('can:customers.view')
    ->name('customers.request-edit');

Route::post('/customers/{customer}/request-edit', [CustomerController::class, 'storeEditRequest'])
    ->middleware('can:customers.view')
    ->name('customers.request-edit.store');

Route::put('/customers/{customer}', [CustomerController::class, 'update'])
    ->middleware('can:customers.update')
    ->name('customers.update');

Route::post('/customers/{customer}/activate', [CustomerController::class, 'activate'])
    ->middleware('can:customers.update')
    ->name('customers.activate');

Route::post('/customers/{customer}/deactivate', [CustomerController::class, 'deactivate'])
    ->middleware('can:customers.deactivate')
    ->name('customers.deactivate');

Route::get('/customers/{customer}', [CustomerController::class, 'show'])
    ->middleware('can:customers.view')
    ->name('customers.show');
