<?php

use App\Http\Controllers\Inspiration\ExploreController;
use App\Http\Controllers\Inspiration\MoodboardController;
use App\Http\Controllers\Inspiration\SavedImageController;
use App\Http\Controllers\Inspiration\SettingsController;
use App\Http\Controllers\Inspiration\SourceAuthController;
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

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('sources/{source}/test', [SettingsController::class, 'test'])->name('sources.test');

        Route::post('sources/{source}/auth', [SourceAuthController::class, 'store'])->name('sources.auth.store');
        Route::delete('sources/{source}/auth', [SourceAuthController::class, 'destroy'])->name('sources.auth.destroy');
    });
