<?php

use App\Modules\PrintTemplates\Controllers\PrintTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/print-templates', [PrintTemplateController::class, 'index'])->middleware('can:print-templates.view')->name('print-templates.index');
Route::get('/print-templates/{template}/preview', [PrintTemplateController::class, 'preview'])->middleware('can:print-templates.view')->name('print-templates.preview');
Route::put('/print-templates/{template}', [PrintTemplateController::class, 'update'])->middleware('can:print-templates.update')->name('print-templates.update');
Route::post('/print-templates/{template}/reset', [PrintTemplateController::class, 'reset'])->middleware('can:print-templates.reset')->name('print-templates.reset');
