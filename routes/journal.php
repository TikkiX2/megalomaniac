<?php

use App\Http\Controllers\Journal\DiaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('diario')->name('journal.')->group(function () {
    Route::get('/', [DiaryController::class, 'index'])->name('index');
    Route::get('{date}', [DiaryController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('show');
    Route::put('{date}', [DiaryController::class, 'update'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('day.update');
});
