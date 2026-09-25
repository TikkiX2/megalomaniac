<?php

use App\Http\Controllers\Agents\AgentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
    Route::post('agents', [AgentController::class, 'store'])->name('agents.store');
    Route::get('agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
    Route::patch('agents/{agent}', [AgentController::class, 'update'])->name('agents.update');
    Route::delete('agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
    Route::post('agents/{agent}/toggle', [AgentController::class, 'toggle'])->name('agents.toggle');
    Route::post('agents/{agent}/run', [AgentController::class, 'run'])->middleware('throttle:6,1')->name('agents.run');
});
