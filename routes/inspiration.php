<?php

use App\Http\Controllers\Inspiration\ExploreController;
use App\Http\Controllers\Inspiration\MoodboardController;
use App\Http\Controllers\Inspiration\SavedImageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('inspiration')
    ->name('inspiration.')
    ->group(function () {
        Route::get('/', [ExploreController::class, 'index'])->name('explore');
        Route::get('search', [ExploreController::class, 'search'])->name('search');

        Route::post('save', [SavedImageController::class, 'store'])->name('save');
        Route::delete('saved/{saved_image}', [SavedImageController::class, 'destroy'])->name('saved.destroy');
        Route::post('saved/{saved_image}/download', [SavedImageController::class, 'download'])->name('saved.download');

        Route::get('moodboards/{moodboard}', [MoodboardController::class, 'show'])->name('moodboards.show');

        // Tasks 10 appends settings here.
    });
