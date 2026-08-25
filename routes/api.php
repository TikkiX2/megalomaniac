<?php

use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\GroceryController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\SupplementController;
use App\Http\Controllers\Api\V1\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Fitness
    Route::get('workouts', [WorkoutController::class, 'index']);
    Route::post('workouts', [WorkoutController::class, 'store']);
    Route::get('workouts/{workout}', [WorkoutController::class, 'show']);
    Route::patch('workouts/{workout}', [WorkoutController::class, 'update']);
    Route::delete('workouts/{workout}', [WorkoutController::class, 'destroy']);

    Route::get('exercises', [ExerciseController::class, 'index']);
    Route::post('exercises', [ExerciseController::class, 'store']);

    Route::get('routines', [RoutineController::class, 'index']);
    Route::post('routines', [RoutineController::class, 'store']);

    // Nutrition
    Route::get('foods', [NutritionController::class, 'searchFoods']);
    Route::get('nutrition/daily', [NutritionController::class, 'getDailyLog']);
    Route::post('nutrition/meal-logs/{mealLog}/items', [NutritionController::class, 'storeMealItem']);

    // Supplements
    Route::get('supplements', [SupplementController::class, 'index']);
    Route::post('supplements', [SupplementController::class, 'store']);
    Route::post('supplements/{supplement}/log', [SupplementController::class, 'logIntake']);
    Route::get('supplements/{supplement}/logs', [SupplementController::class, 'getLogs']);

    // Grocery
    Route::get('grocery', [GroceryController::class, 'index']);
    Route::post('grocery', [GroceryController::class, 'store']);
    Route::get('grocery/{item}', [GroceryController::class, 'show']);
    Route::patch('grocery/{item}', [GroceryController::class, 'update']);
    Route::delete('grocery/{item}', [GroceryController::class, 'destroy']);
    Route::post('grocery/{item}/consume', [GroceryController::class, 'consume']);
});
