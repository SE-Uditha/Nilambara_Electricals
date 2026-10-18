<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Future module routes inherit web middleware, CSRF protection, and auth.
    foreach (glob(__DIR__.'/modules/*.php') ?: [] as $routeFile) {
        require $routeFile;
    }
});