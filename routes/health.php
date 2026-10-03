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
    Route::resource('studies', StudyController::class)->except(['show']);

    Route::post('studies/{study}/attachments', [StudyController::class, 'uploadFile'])
        ->name('studies.attachments.store');
    Route::get('studies/{study}/attachments/{media}/download', [StudyController::class, 'downloadFile'])
        ->name('studies.attachments.download')->whereNumber('media');
    Route::delete('studies/{study}/attachments/{media}', [StudyController::class, 'deleteFile'])
        ->name('studies.attachments.destroy')->whereNumber('media');

    Route::get('studies/{study}', [StudyController::class, 'show'])->name('studies.show');

    Route::resource('appointments', AppointmentController::class)->except(['show']);

    Route::post('appointments/{appointment}/attachments', [AppointmentController::class, 'uploadFile'])
        ->name('appointments.attachments.store');
    Route::get('appointments/{appointment}/attachments/{media}/download', [AppointmentController::class, 'downloadFile'])
        ->name('appointments.attachments.download')->whereNumber('media');
    Route::delete('appointments/{appointment}/attachments/{media}', [AppointmentController::class, 'deleteFile'])
        ->name('appointments.attachments.destroy')->whereNumber('media');

    Route::get('appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
});
