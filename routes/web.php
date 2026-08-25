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
    $userId = auth()->id();

    // Weekly volume: last 7 days including today, sum(weight*reps) per day from completed or any sets
    $start = now()->copy()->subDays(6)->startOfDay();
    $weeklyWorkouts = \App\Models\Workout::with('exercises.sets')
        ->where('user_id', $userId)
        ->where('started_at', '>=', $start)
        ->get();

    // Build map date(Y-m-d) => volume
    $volMap = [];
    $labels = [];
    for ($i = 0; $i < 7; $i++) {
        $d = $start->copy()->addDays($i);
        $key = $d->toDateString();
        $volMap[$key] = 0;
        $labels[$key] = $d->format('D');
    }

    $datesWithWorkout = [];
    foreach ($weeklyWorkouts as $w) {
        $key = $w->started_at->toDateString();
        if (! isset($volMap[$key])) {
            continue;
        }
        $vol = 0;
        foreach ($w->exercises as $we) {
            foreach ($we->sets as $set) {
                $weight = is_numeric($set->weight) ? (float) $set->weight : 0;
                $reps = is_numeric($set->reps) ? (int) $set->reps : 0;
                if ($weight > 0 && $reps > 0) {
                    $vol += $weight * $reps;
                }
            }
        }
        $volMap[$key] += $vol;
        $datesWithWorkout[$key] = true;
    }

    $weeklyVolumeByDay = [];
    $weeklyVolumes = [];
    foreach ($volMap as $date => $vol) {
        $weeklyVolumes[] = $vol;
        $weeklyVolumeByDay[] = ['date' => $date, 'label' => $labels[$date], 'volume' => $vol];
    }

    // Streak: consecutive days with workout ending today backwards
    $streakDays = [];
    $currentStreak = 0;
    $stillStreaking = true;
    // Iterate from today backwards
    for ($i = 6; $i >= 0; $i--) {
        $d = $start->copy()->addDays($i);
        $key = $d->toDateString();
        $has = isset($datesWithWorkout[$key]);
        array_unshift($streakDays, ['date' => $key, 'label' => $labels[$key], 'hasWorkout' => $has]);
        // We'll compute currentStreak after building; simpler forward from today
    }
    // Compute currentStreak from today backwards
    for ($i = 6; $i >= 0; $i--) {
        $d = $start->copy()->addDays($i);
        $key = $d->toDateString();
        if (isset($datesWithWorkout[$key])) {
            $currentStreak++;
        } else {
            if ($i === 6 && $currentStreak === 0) {
                // today missing -> break, streak 0
                break;
            } elseif ($i !== 6) {
                break;
            } else {
                break;
            }
        }
        // If today missing, loop breaks immediately with 0
        // If today present, continues
        if ($i === 6 && ! isset($datesWithWorkout[$key])) {
            $currentStreak = 0;
            break;
        }
    }
    // Correct if today missing, currentStreak should be 0; above handles
    // But for incomplete above logic, re-evaluate cleanly:
    $currentStreak = 0;
    for ($i = 6; $i >= 0; $i--) {
        $d = $start->copy()->addDays($i);
        $key = $d->toDateString();
        if (isset($datesWithWorkout[$key])) {
            $currentStreak++;
        } else {
            break;
        }
    }

    // Nutrition sync: sum across all MealLogs of today
    $mealLogsToday = \App\Models\MealLog::with('items')->where('user_id', $userId)->where('date', now()->toDateString())->get();
    $caloriesToday = $mealLogsToday->sum(fn ($log) => $log->total_calories ?? 0);
    $macrosToday = ['protein' => 0, 'carbs' => 0, 'fats' => 0];
    foreach ($mealLogsToday as $log) {
        $macros = $log->total_macros ?? ['protein' => 0, 'carbs' => 0, 'fats' => 0];
        $macrosToday['protein'] += (float) ($macros['protein'] ?? 0);
        $macrosToday['carbs'] += (float) ($macros['carbs'] ?? 0);
        $macrosToday['fats'] += (float) ($macros['fats'] ?? 0);
    }
    // Goals centralizados (sincronizados con nutrition)
    $goals = ['calories' => 2400, 'protein' => 180, 'carbs' => 250, 'fats' => 70];

    return Inertia::render('fitness/dashboard', [
        'workoutCount' => \App\Models\Workout::where('user_id', $userId)->count(),
        'recentWorkouts' => \App\Models\Workout::with('routine')->where('user_id', $userId)->orderByDesc('started_at')->limit(5)->get(),
        'caloriesToday' => $caloriesToday,
        'macrosToday' => $macrosToday,
        'goals' => $goals,
        'lowStockSupplements' => \App\Models\Supplement::where('user_id', $userId)->get()->filter->is_low_stock->values(),
        'weeklyVolumeByDay' => $weeklyVolumeByDay,
        'weeklyVolumes' => $weeklyVolumes,
        'weeklyVolumeTotal' => array_sum($weeklyVolumes),
        'streak' => ['current' => $currentStreak, 'days' => $streakDays],
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
        Route::get('history', [\App\Http\Controllers\Gym\WorkoutController::class, 'history'])->name('fitness.history');
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

    // Freelance Routes
    Route::prefix('freelance')->name('freelance.')->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Freelance\FreelanceDashboardController::class, 'index'])->name('dashboard');

        Route::resource('clients', \App\Http\Controllers\Freelance\ClientController::class);

        Route::resource('projects', \App\Http\Controllers\Freelance\ProjectController::class);
        Route::post('projects/{project}/payments', [\App\Http\Controllers\Freelance\ProjectController::class, 'addPayment'])->name('projects.payments.store');
        Route::post('projects/{project}/media', [\App\Http\Controllers\Freelance\ProjectController::class, 'uploadFile'])->name('projects.media.upload');
        Route::get('media/{media}/download', [\App\Http\Controllers\Freelance\ProjectController::class, 'downloadFile'])->name('media.download');
        Route::delete('media/{media}', [\App\Http\Controllers\Freelance\ProjectController::class, 'deleteFile'])->name('media.delete');

        // Comments
        Route::get('projects/{project}/comments', [\App\Http\Controllers\Freelance\ProjectCommentController::class, 'index'])->name('projects.comments.index');
        Route::post('projects/{project}/comments', [\App\Http\Controllers\Freelance\ProjectCommentController::class, 'store'])->name('projects.comments.store');
        Route::patch('comments/{comment}', [\App\Http\Controllers\Freelance\ProjectCommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [\App\Http\Controllers\Freelance\ProjectCommentController::class, 'destroy'])->name('comments.destroy');

        Route::resource('quotes', \App\Http\Controllers\Freelance\QuoteController::class);
        Route::get('quotes/{quote}/pdf', [\App\Http\Controllers\Freelance\QuoteController::class, 'generatePDF'])->name('quotes.pdf');
        Route::post('quotes/{quote}/duplicate', [\App\Http\Controllers\Freelance\QuoteController::class, 'duplicate'])->name('quotes.duplicate');
        Route::post('quotes/{quote}/convert', [\App\Http\Controllers\Freelance\QuoteController::class, 'convertToProject'])->name('quotes.convert');

        Route::resource('projects.tasks', \App\Http\Controllers\Freelance\ProjectTaskController::class)->shallow();
        Route::post('tasks/{task}/sync-to-notion', [\App\Http\Controllers\Freelance\ProjectTaskController::class, 'syncToNotion'])->name('tasks.sync-to-notion');
        Route::post('tasks/{task}/sync-from-notion', [\App\Http\Controllers\Freelance\ProjectTaskController::class, 'syncFromNotion'])->name('tasks.sync-from-notion');

        Route::post('notion/webhook', [\App\Http\Controllers\Freelance\ProjectTaskController::class, 'notionWebhook'])
            ->name('notion.webhook')
            ->withoutMiddleware(['auth', 'verified']);
    });

    // Personal Tasks & Projects Routes
    Route::prefix('personal')->name('personal.')->group(function () {
        Route::resource('projects', \App\Http\Controllers\Personal\PersonalProjectController::class);
        Route::post('projects/{project}/milestones', [\App\Http\Controllers\Personal\PersonalProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
        Route::resource('tasks', \App\Http\Controllers\Personal\PersonalTaskController::class)->except(['create', 'edit']);
        Route::patch('tasks/{task}/move', [\App\Http\Controllers\Personal\PersonalTaskController::class, 'move'])->name('tasks.move');
        Route::post('tasks/{task}/properties', [\App\Http\Controllers\Personal\TaskPropertyController::class, 'store'])->name('tasks.properties.store');
        Route::patch('task-properties/{property}', [\App\Http\Controllers\Personal\TaskPropertyController::class, 'update'])->name('task-properties.update');
        Route::delete('task-properties/{property}', [\App\Http\Controllers\Personal\TaskPropertyController::class, 'destroy'])->name('task-properties.destroy');
        Route::resource('saved-views', \App\Http\Controllers\Personal\TaskSavedViewController::class)->only(['index', 'store', 'destroy']);
    });
});
