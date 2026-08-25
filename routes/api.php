<?php

use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('workouts', [WorkoutController::class, 'index']);
    Route::post('workouts', [WorkoutController::class, 'store']);
    Route::get('workouts/{workout}', [WorkoutController::class, 'show']);
    Route::patch('workouts/{workout}', [WorkoutController::class, 'update']);
    Route::delete('workouts/{workout}', [WorkoutController::class, 'destroy']);

    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::post('exercises', [ExerciseController::class, 'store']);

    Route::get('routines', [RoutineController::class, 'index']);
    Route::post('routines', [RoutineController::class, 'store']);
});
