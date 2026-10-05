<?php

use App\Http\Controllers\Inspiration\ExploreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])
    ->prefix('inspiration')
    ->name('inspiration.')
    ->group(function () {
        Route::get('/', [ExploreController::class, 'index'])->name('explore');
        Route::get('search', [ExploreController::class, 'search'])->name('search');

        // Tasks 7/8/10 append save/destroy/download/settings/moodboards here.
    });
