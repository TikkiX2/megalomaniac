<?php

use App\Http\Controllers\Integrations\ActivityController;
use App\Http\Controllers\Integrations\ApprovalController;
use App\Http\Controllers\Integrations\OAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('integrations/approvals', [ApprovalController::class, 'index'])->name('integrations.approvals.index');
    Route::post('integrations/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->middleware('throttle:30,1')->name('integrations.approvals.approve');
    Route::post('integrations/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->middleware('throttle:30,1')->name('integrations.approvals.reject');
    Route::get('integrations/activity', [ActivityController::class, 'index'])->name('integrations.activity.index');

    Route::get('integrations/oauth/{connection}/redirect', [OAuthController::class, 'redirect'])->name('integrations.oauth.redirect');
    Route::get('integrations/oauth/{connection}/callback', [OAuthController::class, 'callback'])->name('integrations.oauth.callback');
});
