<?php

use App\Modules\Approvals\Controllers\ApprovalController;
use Illuminate\Support\Facades\Route;

Route::get('/authorized-approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::get('/authorized-approvals/{kind}/{id}', [ApprovalController::class, 'document'])->whereIn('kind', ['purchase', 'pos_payment'])->whereNumber('id')->name('approvals.document');
Route::post('/authorized-approvals/{kind}/{id}', [ApprovalController::class, 'decideDocument'])->whereIn('kind', ['purchase', 'pos_payment'])->whereNumber('id')->name('approvals.document.decide');
Route::get('/approval-requests', [ApprovalController::class, 'mine'])->name('approval-requests.index');
Route::get('/approval-requests/create', [ApprovalController::class, 'create'])->name('approval-requests.create');
Route::post('/approval-requests', [ApprovalController::class, 'store'])->name('approval-requests.store');
Route::get('/approval-requests/{approval}', [ApprovalController::class, 'show'])->whereNumber('approval')->name('approval-requests.show');
Route::post('/approval-requests/{approval}/decision', [ApprovalController::class, 'decide'])->whereNumber('approval')->name('approval-requests.decide');
