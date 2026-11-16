<?php

use App\Http\Controllers\Journal\CalendarController;
use App\Http\Controllers\Journal\DiaryController;
use App\Http\Controllers\Journal\MomentController;
use App\Http\Controllers\Journal\PhotoController;
use App\Http\Controllers\Journal\ReflectionController;
use App\Http\Controllers\Journal\ReviewController;
use App\Http\Controllers\Journal\SearchController;
use App\Http\Controllers\Journal\TimelineController;
use App\Http\Controllers\Journal\UnlockController;
use App\Models\JournalMoment;
use Illuminate\Support\Facades\Route;

Route::bind('moment', function (string $value): JournalMoment {
    return JournalMoment::query()
        ->where('user_id', request()->user()?->id)
        ->whereKey($value)
        ->firstOrFail();
});

Route::middleware(['auth', 'verified'])->prefix('diario')->name('journal.')->group(function () {
    Route::get('/', [DiaryController::class, 'index'])->name('index');

    Route::get('calendario', CalendarController::class)->name('calendar');
    Route::get('todas', TimelineController::class)->name('timeline');
    Route::get('buscar', SearchController::class)->name('search');
    Route::get('repaso', ReviewController::class)->name('review');

    Route::post('reflexiones', [ReflectionController::class, 'store'])
        ->middleware('throttle:3,1')
        ->name('reflections.store');

    Route::post('desbloquear', [UnlockController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('unlock');

    Route::post('{date}/momentos', [MomentController::class, 'store'])
        ->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')
        ->name('moments.store');
    Route::patch('momentos/{moment}', [MomentController::class, 'update'])->name('moments.update');
    Route::delete('momentos/{moment}', [MomentController::class, 'destroy'])->name('moments.destroy');

    Route::post('momentos/{moment}/fotos', [PhotoController::class, 'store'])->name('photos.store');
    Route::get('momentos/{moment}/fotos/{media}', [PhotoController::class, 'show'])->name('photos.show');
    Route::delete('momentos/{moment}/fotos/{media}', [PhotoController::class, 'destroy'])->name('photos.destroy');

    Route::get('{date}', [DiaryController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('show');
    Route::put('{date}', [DiaryController::class, 'update'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('day.update');
});
