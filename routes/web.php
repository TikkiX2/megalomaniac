<?php

use App\Http\Controllers\AgentSuggestionController;
use App\Http\Controllers\Ai\AiFitnessController;
use App\Http\Controllers\Ai\ChatController;
use App\Http\Controllers\AiInsightController;
use App\Http\Controllers\Finance\CreditCardController;
use App\Http\Controllers\Finance\CurrencyController;
use App\Http\Controllers\Finance\CurrencyExchangeController;
use App\Http\Controllers\Finance\DashboardController;
use App\Http\Controllers\Finance\DebtController;
use App\Http\Controllers\Finance\ExchangeRateController;
use App\Http\Controllers\Finance\FinanceStatisticsController;
use App\Http\Controllers\Finance\IncomeController;
use App\Http\Controllers\Finance\IncomeSourceController;
use App\Http\Controllers\Finance\PurchaseCategoryController;
use App\Http\Controllers\Finance\PurchaseController;
use App\Http\Controllers\Finance\SavingsReserveController;
use App\Http\Controllers\Finance\WithdrawalCategoryController;
use App\Http\Controllers\Finance\WithdrawalController;
use App\Http\Controllers\Freelance\ClientController;
use App\Http\Controllers\Freelance\FreelanceDashboardController;
use App\Http\Controllers\Freelance\ProjectCommentController;
use App\Http\Controllers\Freelance\ProjectController;
use App\Http\Controllers\Freelance\ProjectTaskController;
use App\Http\Controllers\Freelance\QuoteController;
use App\Http\Controllers\Grocery\GroceryController;
use App\Http\Controllers\Gym\ExerciseController;
use App\Http\Controllers\Gym\RoutineController;
use App\Http\Controllers\Gym\WorkoutController;
use App\Http\Controllers\Nutrition\NutritionController;
use App\Http\Controllers\Personal\PersonalProjectController;
use App\Http\Controllers\Personal\PersonalTaskController;
use App\Http\Controllers\Personal\TaskPropertyController;
use App\Http\Controllers\Personal\TaskSavedViewController;
use App\Http\Controllers\Supplement\SupplementController;
use App\Models\MealLog;
use App\Models\Supplement;
use App\Models\Workout;
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
    $weeklyWorkouts = Workout::with('exercises.sets')
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
    $mealLogsToday = MealLog::with('items')->where('user_id', $userId)->where('date', now()->toDateString())->get();
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
        'workoutCount' => Workout::where('user_id', $userId)->count(),
        'recentWorkouts' => Workout::with('routine')->where('user_id', $userId)->orderByDesc('started_at')->limit(5)->get(),
        'caloriesToday' => $caloriesToday,
        'macrosToday' => $macrosToday,
        'goals' => $goals,
        'lowStockSupplements' => Supplement::where('user_id', $userId)->get()->filter->is_low_stock->values(),
        'weeklyVolumeByDay' => $weeklyVolumeByDay,
        'weeklyVolumes' => $weeklyVolumes,
        'weeklyVolumeTotal' => array_sum($weeklyVolumes),
        'streak' => ['current' => $currentStreak, 'days' => $streakDays],
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/integrations.php';
require __DIR__.'/agents.php';
require __DIR__.'/feed.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // AI Chat
    Route::get('ai/chat', [ChatController::class, 'index'])->name('ai.chat.index');
    Route::get('ai/chat/{thread}', [ChatController::class, 'show'])->name('ai.chat.show');
    Route::patch('ai/chat/{thread}', [ChatController::class, 'update'])->name('ai.chat.update');
    Route::delete('ai/chat/{thread}', [ChatController::class, 'destroy'])->name('ai.chat.destroy');
    Route::post('ai/chat', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('ai.chat.send');
    Route::post('ai/chat/{thread}/regenerate', [ChatController::class, 'regenerate'])->middleware('throttle:30,1')->name('ai.chat.regenerate');
    Route::post('ai/chat/{thread}/edit', [ChatController::class, 'edit'])->middleware('throttle:30,1')->name('ai.chat.edit');
    Route::get('ai/models', [ChatController::class, 'models'])->name('ai.models');

    // AI Suggestions Routes
    Route::get('ai/suggestions', [AgentSuggestionController::class, 'index'])->name('ai.suggestions.index');
    Route::post('ai/suggestions/{suggestion}/dismiss', [AgentSuggestionController::class, 'dismiss'])->name('ai.suggestions.dismiss');

    // AI Insights Routes
    Route::get('ai/insights/workout', [AiInsightController::class, 'workout'])->name('ai.insights.workout');
    Route::get('ai/insights/finance', [AiInsightController::class, 'finance'])->name('ai.insights.finance');
    Route::get('ai/insights/nutrition', [AiInsightController::class, 'nutrition'])->name('ai.insights.nutrition');
    Route::get('ai/insights/grocery', [AiInsightController::class, 'grocery'])->name('ai.insights.grocery');
    Route::get('ai/insights/tasks', [AiInsightController::class, 'tasks'])->name('ai.insights.tasks');

    // AI Fitness Routes
    Route::get('ai/suggest-meal', [AiFitnessController::class, 'suggestMeal'])->name('ai.suggest-meal');
    Route::get('ai/generate-routine', [AiFitnessController::class, 'generateRoutine'])->name('ai.generate-routine');

    // AI Freelance Routes
    Route::post('ai/generate-quote', [AiInsightController::class, 'generateQuote'])->name('ai.generate-quote');

    // Gym Routes
    Route::prefix('gym')->group(function () {
        Route::apiResource('exercises', ExerciseController::class);
        Route::apiResource('routines', RoutineController::class);
        Route::apiResource('workouts', WorkoutController::class);
        Route::post('workouts/{workout}/exercises', [WorkoutController::class, 'addExercise']);
        Route::post('workout-exercises/{workoutExercise}/sets', [WorkoutController::class, 'logSet']);
    });

    // Nutrition Routes
    Route::prefix('nutrition')->group(function () {
        Route::get('foods/search', [NutritionController::class, 'searchFoods']);
        Route::post('foods', [NutritionController::class, 'storeFood']);
        Route::get('logs', [NutritionController::class, 'getDailyLog']);
        Route::post('logs/items', [NutritionController::class, 'storeMealItem']);
        Route::delete('logs/items/{mealItem}', [NutritionController::class, 'deleteMealItem']);
    });

    // Supplement Routes
    Route::prefix('supplements')->group(function () {
        Route::apiResource('items', SupplementController::class)->names('supplements.items');
        Route::post('{supplement}/log', [SupplementController::class, 'logIntake']);
        Route::get('logs', [SupplementController::class, 'getLogs']);
    });

    // Fitness App Managed Routes
    Route::prefix('fitness')->group(function () {
        Route::get('history', [WorkoutController::class, 'history'])->name('fitness.history');
        Route::get('gym', [ExerciseController::class, 'index'])->name('fitness.gym');
        Route::get('routines', [RoutineController::class, 'index'])->name('fitness.routines');
        Route::post('routines', [RoutineController::class, 'store']);
        Route::get('nutrition', [NutritionController::class, 'index'])->name('fitness.nutrition');
        Route::get('supplements', [SupplementController::class, 'index'])->name('fitness.supplements');
        Route::get('groceries', [GroceryController::class, 'index'])->name('fitness.groceries');
    });

    // Grocery Routes
    Route::prefix('grocery')->group(function () {
        Route::get('history', [GroceryController::class, 'history'])->name('grocery.history');
        Route::post('bulk-restock', [GroceryController::class, 'bulkRestock'])->name('grocery.bulk-restock');
        Route::apiResource('items', GroceryController::class)->names('grocery.items');
        Route::post('{item}/consume', [GroceryController::class, 'consume'])->name('grocery.consume');
    });

    // Finance Routes
    Route::prefix('finance')->name('finance.')->group(function () {
        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Purchases
        Route::resource('purchases', PurchaseController::class);

        // Incomes
        Route::resource('incomes', IncomeController::class);

        // Debts
        Route::resource('debts', DebtController::class);
        Route::post('debts/{debt}/payments', [DebtController::class, 'addPayment'])->name('debts.payments.store');

        // Credit Cards
        Route::resource('credit-cards', CreditCardController::class);

        // Currencies
        Route::resource('currencies', CurrencyController::class);
        Route::patch('currencies/{currency}/restore', [CurrencyController::class, 'restore'])->name('currencies.restore');

        // Exchange Rates
        Route::resource('exchange-rates', ExchangeRateController::class);
        Route::post('exchange-rates/convert', [ExchangeRateController::class, 'convert'])->name('exchange-rates.convert');

        // Income Sources
        Route::resource('income-sources', IncomeSourceController::class);

        // Purchase Categories
        Route::resource('categories', PurchaseCategoryController::class);

        // Statistics
        Route::get('statistics', [FinanceStatisticsController::class, 'index'])->name('statistics');

        // Withdrawals
        Route::resource('withdrawal-categories', WithdrawalCategoryController::class);
        Route::resource('withdrawals', WithdrawalController::class);

        // Savings Reserves
        Route::resource('savings-reserves', SavingsReserveController::class)->parameters([
            'savings-reserves' => 'savings_reserve',
        ]);
        Route::post('savings-reserves/{savings_reserve}/deposit', [SavingsReserveController::class, 'deposit'])->name('savings-reserves.deposit');
        Route::post('savings-reserves/{savings_reserve}/withdraw', [SavingsReserveController::class, 'withdraw'])->name('savings-reserves.withdraw');

        // Currency Exchanges
        Route::resource('currency-exchanges', CurrencyExchangeController::class);
    });

    // Freelance Routes
    Route::prefix('freelance')->name('freelance.')->group(function () {
        Route::get('dashboard', [FreelanceDashboardController::class, 'index'])->name('dashboard');

        Route::resource('clients', ClientController::class);

        Route::resource('projects', ProjectController::class);
        Route::post('projects/{project}/payments', [ProjectController::class, 'addPayment'])->name('projects.payments.store');
        Route::post('projects/{project}/media', [ProjectController::class, 'uploadFile'])->name('projects.media.upload');
        Route::get('media/{media}/download', [ProjectController::class, 'downloadFile'])->name('media.download');
        Route::delete('media/{media}', [ProjectController::class, 'deleteFile'])->name('media.delete');

        // Comments
        Route::get('projects/{project}/comments', [ProjectCommentController::class, 'index'])->name('projects.comments.index');
        Route::post('projects/{project}/comments', [ProjectCommentController::class, 'store'])->name('projects.comments.store');
        Route::patch('comments/{comment}', [ProjectCommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [ProjectCommentController::class, 'destroy'])->name('comments.destroy');

        Route::resource('quotes', QuoteController::class);
        Route::get('quotes/{quote}/pdf', [QuoteController::class, 'generatePDF'])->name('quotes.pdf');
        Route::post('quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
        Route::post('quotes/{quote}/convert', [QuoteController::class, 'convertToProject'])->name('quotes.convert');

        Route::resource('projects.tasks', ProjectTaskController::class)->shallow();
        Route::post('tasks/{task}/sync-to-notion', [ProjectTaskController::class, 'syncToNotion'])->name('tasks.sync-to-notion');
        Route::post('tasks/{task}/sync-from-notion', [ProjectTaskController::class, 'syncFromNotion'])->name('tasks.sync-from-notion');

        Route::post('notion/webhook', [ProjectTaskController::class, 'notionWebhook'])
            ->name('notion.webhook')
            ->withoutMiddleware(['auth', 'verified']);
    });

    // Personal Tasks & Projects Routes
    Route::prefix('personal')->name('personal.')->group(function () {
        Route::resource('projects', PersonalProjectController::class);
        Route::post('projects/{project}/milestones', [PersonalProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
        Route::resource('tasks', PersonalTaskController::class)->except(['create', 'edit']);
        Route::patch('tasks/{task}/move', [PersonalTaskController::class, 'move'])->name('tasks.move');
        Route::post('tasks/{task}/properties', [TaskPropertyController::class, 'store'])->name('tasks.properties.store');
        Route::patch('task-properties/{property}', [TaskPropertyController::class, 'update'])->name('task-properties.update');
        Route::delete('task-properties/{property}', [TaskPropertyController::class, 'destroy'])->name('task-properties.destroy');
        Route::resource('saved-views', TaskSavedViewController::class)->only(['index', 'store', 'destroy']);
    });
});
