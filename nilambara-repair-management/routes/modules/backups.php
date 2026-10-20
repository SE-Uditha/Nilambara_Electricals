<?php

use App\Modules\BackupRestore\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::get('/backups', [BackupController::class, 'index'])->middleware('can:backups.view')->name('backups.index');
Route::post('/backups', [BackupController::class, 'create'])->middleware('can:backups.create')->name('backups.create');
Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->middleware('can:backups.download')->name('backups.download');
Route::get('/admin/backups', [BackupController::class, 'index'])->middleware('can:backups.view');
Route::post('/admin/backups/configure', [BackupController::class, 'configure'])->middleware('can:backups.configure')->name('backups.configure');
Route::post('/admin/backups/{backup}/verify', [BackupController::class, 'verify'])->middleware('can:backups.view')->name('backups.verify');
