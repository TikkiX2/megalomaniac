<?php

use App\Http\Controllers\People\PersonController;
use App\Http\Controllers\People\PersonInteractionController;
use App\Http\Controllers\People\PersonKeyDateController;
use App\Http\Controllers\People\PersonSocialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('people')->name('people.')->group(function () {
    Route::get('/', [PersonController::class, 'index'])->name('index');
    Route::get('create', [PersonController::class, 'create'])->name('create');
    Route::post('/', [PersonController::class, 'store'])->name('store');
    Route::get('calendar', [PersonController::class, 'calendar'])->name('calendar');
    Route::get('timeline', [PersonController::class, 'timeline'])->name('timeline');

    Route::get('{person}', [PersonController::class, 'show'])->name('show');
    Route::get('{person}/edit', [PersonController::class, 'edit'])->name('edit');
    Route::put('{person}', [PersonController::class, 'update'])->name('update');
    Route::delete('{person}', [PersonController::class, 'destroy'])->name('destroy');

    Route::post('{person}/avatar', [PersonController::class, 'uploadAvatar'])->name('avatar.store');
    Route::delete('{person}/avatar', [PersonController::class, 'destroyAvatar'])->name('avatar.destroy');

    Route::post('{person}/contacted', [PersonInteractionController::class, 'quickLog'])->name('contacted');
    Route::post('{person}/interactions', [PersonInteractionController::class, 'store'])->name('interactions.store');
    Route::delete('interactions/{interaction}', [PersonInteractionController::class, 'destroy'])->name('interactions.destroy');

    Route::post('{person}/key-dates', [PersonKeyDateController::class, 'store'])->name('key-dates.store');
    Route::patch('key-dates/{keyDate}', [PersonKeyDateController::class, 'update'])->name('key-dates.update');
    Route::delete('key-dates/{keyDate}', [PersonKeyDateController::class, 'destroy'])->name('key-dates.destroy');

    Route::post('{person}/socials', [PersonSocialController::class, 'store'])->name('socials.store');
    Route::patch('socials/{social}', [PersonSocialController::class, 'update'])->name('socials.update');
    Route::delete('socials/{social}', [PersonSocialController::class, 'destroy'])->name('socials.destroy');
});
