<?php

use App\Modules\RolesPermissions\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/roles')->name('admin.roles.')->middleware('can:roles.view')->group(function (): void {
    Route::get('/', [RoleController::class, 'index'])->name('index');
    Route::get('/{role}/edit', [RoleController::class, 'edit'])->middleware('can:roles.manage')->name('edit');
    Route::put('/{role}', [RoleController::class, 'update'])->middleware('can:roles.manage')->name('update');
});
