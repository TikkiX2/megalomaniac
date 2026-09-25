<?php

use App\Http\Controllers\Storage\StorageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('storage', [StorageController::class, 'index'])->name('storage.index');
    Route::get('storage/browse', [StorageController::class, 'browse'])->name('storage.browse');
    Route::get('storage/download', [StorageController::class, 'download'])->name('storage.download');
    Route::post('storage/upload', [StorageController::class, 'upload'])->name('storage.upload');
    Route::post('storage/mkdir', [StorageController::class, 'mkdir'])->name('storage.mkdir');
    Route::post('storage/move', [StorageController::class, 'move'])->name('storage.move');
    Route::delete('storage/file', [StorageController::class, 'destroy'])->name('storage.destroy');
    Route::post('storage/share', [StorageController::class, 'share'])->name('storage.share');
});
