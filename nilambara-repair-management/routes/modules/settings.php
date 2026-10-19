<?php

use App\Modules\Settings\Controllers\SystemSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SystemSettingController::class, 'index'])->middleware('can:settings.view')->name('settings.index');
Route::put('/settings', [SystemSettingController::class, 'update'])->middleware('can:settings.update')->name('settings.update');
