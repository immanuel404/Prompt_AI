<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIController;

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');

Route::get('/', [AIController::class, 'index'])->name('ai.index');
Route::post('/ai/submit', [AIController::class, 'submitPrompt'])->name('ai.submit');
