<?php

use App\Modules\BackupRestore\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::get('/backups', [BackupController::class, 'index'])->middleware('can:backups.view')->name('backups.index');
Route::post('/backups', [BackupController::class, 'create'])->middleware('can:backups.create')->name('backups.create');
Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->middleware('can:backups.view')->name('backups.download');
