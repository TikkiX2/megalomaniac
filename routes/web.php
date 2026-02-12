<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('fitness/dashboard', [
        'workoutCount' => \App\Models\Workout::where('user_id', auth()->id())->count(),
        'recentWorkouts' => \App\Models\Workout::with('routine')->where('user_id', auth()->id())->orderByDesc('started_at')->limit(5)->get(),
        'caloriesToday' => \App\Models\MealLog::where('user_id', auth()->id())->where('date', now()->toDateString())->first()?->total_calories ?? 0,
        'lowStockSupplements' => \App\Models\Supplement::where('user_id', auth()->id())->get()->filter->is_low_stock->values(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // Gym Routes
    Route::prefix('gym')->group(function () {
        Route::apiResource('exercises', \App\Http\Controllers\Gym\ExerciseController::class);
        Route::apiResource('routines', \App\Http\Controllers\Gym\RoutineController::class);
        Route::apiResource('workouts', \App\Http\Controllers\Gym\WorkoutController::class);
        Route::post('workouts/{workout}/exercises', [\App\Http\Controllers\Gym\WorkoutController::class, 'addExercise']);
        Route::post('workout-exercises/{workoutExercise}/sets', [\App\Http\Controllers\Gym\WorkoutController::class, 'logSet']);
    });

    // Nutrition Routes
    Route::prefix('nutrition')->group(function () {
        Route::get('foods/search', [\App\Http\Controllers\Nutrition\NutritionController::class, 'searchFoods']);
        Route::post('foods', [\App\Http\Controllers\Nutrition\NutritionController::class, 'storeFood']);
        Route::get('logs', [\App\Http\Controllers\Nutrition\NutritionController::class, 'getDailyLog']);
        Route::post('logs/items', [\App\Http\Controllers\Nutrition\NutritionController::class, 'storeMealItem']);
        Route::delete('logs/items/{mealItem}', [\App\Http\Controllers\Nutrition\NutritionController::class, 'deleteMealItem']);
    });

    // Supplement Routes
    Route::prefix('supplements')->group(function () {
        Route::apiResource('items', \App\Http\Controllers\Supplement\SupplementController::class)->names('supplements.items');
        Route::post('{supplement}/log', [\App\Http\Controllers\Supplement\SupplementController::class, 'logIntake']);
        Route::get('logs', [\App\Http\Controllers\Supplement\SupplementController::class, 'getLogs']);
    });

    // Fitness App Managed Routes
    Route::prefix('fitness')->group(function () {
        Route::get('gym', [\App\Http\Controllers\Gym\ExerciseController::class, 'index'])->name('fitness.gym');
        Route::get('routines', [\App\Http\Controllers\Gym\RoutineController::class, 'index'])->name('fitness.routines');
        Route::post('routines', [\App\Http\Controllers\Gym\RoutineController::class, 'store']);
        Route::get('nutrition', [\App\Http\Controllers\Nutrition\NutritionController::class, 'index'])->name('fitness.nutrition');
        Route::get('supplements', [\App\Http\Controllers\Supplement\SupplementController::class, 'index'])->name('fitness.supplements');
        Route::get('groceries', [\App\Http\Controllers\Grocery\GroceryController::class, 'index'])->name('fitness.groceries');
    });

    // Grocery Routes
    Route::prefix('grocery')->group(function () {
        Route::get('history', [\App\Http\Controllers\Grocery\GroceryController::class, 'history'])->name('grocery.history');
        Route::post('bulk-restock', [\App\Http\Controllers\Grocery\GroceryController::class, 'bulkRestock'])->name('grocery.bulk-restock');
        Route::apiResource('items', \App\Http\Controllers\Grocery\GroceryController::class)->names('grocery.items');
        Route::post('{item}/consume', [\App\Http\Controllers\Grocery\GroceryController::class, 'consume'])->name('grocery.consume');
    });

    // Finance Routes
    Route::prefix('finance')->name('finance.')->group(function () {
        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Finance\DashboardController::class, 'index'])->name('dashboard');

        // Purchases
        Route::resource('purchases', \App\Http\Controllers\Finance\PurchaseController::class);

        // Incomes
        Route::resource('incomes', \App\Http\Controllers\Finance\IncomeController::class);

        // Debts
        Route::resource('debts', \App\Http\Controllers\Finance\DebtController::class);
        Route::post('debts/{debt}/payments', [\App\Http\Controllers\Finance\DebtController::class, 'addPayment'])->name('debts.payments.store');

        // Credit Cards
        Route::resource('credit-cards', \App\Http\Controllers\Finance\CreditCardController::class);

        // Currencies
        Route::resource('currencies', \App\Http\Controllers\Finance\CurrencyController::class);
        Route::patch('currencies/{currency}/restore', [\App\Http\Controllers\Finance\CurrencyController::class, 'restore'])->name('currencies.restore');

        // Exchange Rates
        Route::resource('exchange-rates', \App\Http\Controllers\Finance\ExchangeRateController::class);
        Route::post('exchange-rates/convert', [\App\Http\Controllers\Finance\ExchangeRateController::class, 'convert'])->name('exchange-rates.convert');

        // Income Sources
        Route::resource('income-sources', \App\Http\Controllers\Finance\IncomeSourceController::class);

        // Purchase Categories
        Route::resource('categories', \App\Http\Controllers\Finance\PurchaseCategoryController::class);

        // Statistics
        Route::get('statistics', [\App\Http\Controllers\Finance\FinanceStatisticsController::class, 'index'])->name('statistics');

        // Withdrawals
        Route::resource('withdrawal-categories', \App\Http\Controllers\Finance\WithdrawalCategoryController::class);
        Route::resource('withdrawals', \App\Http\Controllers\Finance\WithdrawalController::class);

        // Savings Reserves
        Route::resource('savings-reserves', \App\Http\Controllers\Finance\SavingsReserveController::class)->parameters([
            'savings-reserves' => 'savings_reserve',
        ]);
        Route::post('savings-reserves/{savings_reserve}/deposit', [\App\Http\Controllers\Finance\SavingsReserveController::class, 'deposit'])->name('savings-reserves.deposit');
        Route::post('savings-reserves/{savings_reserve}/withdraw', [\App\Http\Controllers\Finance\SavingsReserveController::class, 'withdraw'])->name('savings-reserves.withdraw');

        // Currency Exchanges
        Route::resource('currency-exchanges', \App\Http\Controllers\Finance\CurrencyExchangeController::class);
    });
});
