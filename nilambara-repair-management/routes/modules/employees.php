<?php

use App\Modules\Employees\Controllers\EmployeeController;
use App\Modules\Employees\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:employees.view')->group(function (): void {
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
});

Route::middleware('can:employees.create')->group(function (): void {
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
});

Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
    ->middleware('can:employees.view')
    ->name('employees.show');

Route::middleware('can:employees.update')->group(function (): void {
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
});

Route::get('/employees/{employee}/permissions', [EmployeeController::class, 'permissions'])
    ->middleware('can:permissions.manage')
    ->name('employees.permissions');

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('can:users.view')->name('users.show');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:users.update')->name('users.update');
    Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->middleware('can:roles.manage')->name('users.role');
    Route::put('/users/{user}/permissions', [UserController::class, 'updateOverrides'])->middleware('can:permissions.manage')->name('users.permissions');
    Route::post('/users/{user}/status', [UserController::class, 'setEnabled'])->middleware('can:users.disable')->name('users.status');
    Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->middleware('can:users.update')->name('users.password');
    Route::get('/permissions', [UserController::class, 'permissions'])->middleware('can:permissions.manage')->name('permissions.index');
});
