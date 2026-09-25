<?php

use App\Http\Controllers\Feed\FeedController;
use App\Http\Controllers\Feed\FeedSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('feed', [FeedController::class, 'index'])->name('feed.index');
    Route::get('feed/settings', [FeedController::class, 'settings'])->name('feed.settings');
    Route::post('feed/items/{item}/signal', [FeedController::class, 'signal'])->name('feed.signal');
    Route::post('feed/digest', [FeedController::class, 'digestNow'])->middleware('throttle:3,1')->name('feed.digest');
    Route::post('feed/sources', [FeedSourceController::class, 'store'])->name('feed.sources.store');
    Route::patch('feed/sources/{source}', [FeedSourceController::class, 'update'])->name('feed.sources.update');
    Route::delete('feed/sources/{source}', [FeedSourceController::class, 'destroy'])->name('feed.sources.destroy');
});
