<?php

use App\Http\Controllers\Health\AppointmentController;
use App\Http\Controllers\Health\ConditionController;
use App\Http\Controllers\Health\DashboardController;
use App\Http\Controllers\Health\HealthChatController;
use App\Http\Controllers\Health\MeasurementController;
use App\Http\Controllers\Health\MedicationController;
use App\Http\Controllers\Health\MedicationIntakeController;
use App\Http\Controllers\Health\ProfessionalController;
use App\Http\Controllers\Health\StudyController;
use App\Http\Controllers\Health\SymptomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('health')->name('health.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('chats', [HealthChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [HealthChatController::class, 'store'])->name('chats.store');

    Route::resource('conditions', ConditionController::class)->except(['show']);
    Route::resource('medications', MedicationController::class)->except(['show']);
    Route::post('medications/{medication}/intakes', [MedicationIntakeController::class, 'store'])
        ->name('medications.intakes.store');
    Route::delete('medications/{medication}/intakes/{intake}', [MedicationIntakeController::class, 'destroy'])
        ->name('medications.intakes.destroy');
    Route::resource('measurements', MeasurementController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('symptoms', SymptomController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('professionals', ProfessionalController::class)->except(['show']);
    Route::resource('studies', StudyController::class);
    Route::resource('appointments', AppointmentController::class)->except(['show']);
});
