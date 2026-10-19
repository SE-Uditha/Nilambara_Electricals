<?php

use App\Modules\AuditLogs\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('can:audit.view')->name('audit-logs.index');
