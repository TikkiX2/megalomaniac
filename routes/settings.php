<?php

use App\Http\Controllers\Integrations\ConnectionController;
use App\Http\Controllers\Settings\AiSettingsController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/ai', [AiSettingsController::class, 'edit'])->name('ai-settings.edit');
    Route::put('settings/ai', [AiSettingsController::class, 'update'])->name('ai-settings.update');

    Route::get('settings/api-keys', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('settings/api-keys', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('settings/api-keys/{tokenId}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::get('settings/connections', [ConnectionController::class, 'index'])->name('connections.index');
    Route::post('settings/connections', [ConnectionController::class, 'store'])->name('connections.store');
    Route::post('settings/connections/test', [ConnectionController::class, 'test'])->name('connections.test');
    Route::patch('settings/connections/{connection}', [ConnectionController::class, 'update'])->name('connections.update');
    Route::delete('settings/connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');
    Route::get('settings/connections/{connection}/actions', [ConnectionController::class, 'actions'])->name('connections.actions');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});
