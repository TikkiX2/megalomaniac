<?php

use App\Ai\Enums\AiScope;
use App\Http\Controllers\AgentSuggestionController;
use App\Http\Controllers\Ai\AiFitnessController;
use App\Http\Controllers\Ai\ChatAttachmentController;
use App\Http\Controllers\Ai\ChatController;
use App\Http\Controllers\Ai\MemoryController;
use App\Http\Controllers\Ai\SourcesController;
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
use App\Http\Controllers\TaskBoardColumnController;
use App\Http\Controllers\Today\ArchiveBulkController;
use App\Http\Controllers\Today\ArchiveController;
use App\Http\Controllers\Today\QueueController;
use App\Http\Controllers\Today\TodayController;
use App\Http\Controllers\Today\TomorrowController;
use App\Http\Controllers\Today\WeekController;
use App\Models\Supplement;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [TodayController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/people.php';
require __DIR__.'/integrations.php';
require __DIR__.'/agents.php';
require __DIR__.'/feed.php';
require __DIR__.'/storage.php';
require __DIR__.'/health.php';
require __DIR__.'/inspiration.php';

Route::middleware(['auth', 'verified'])->group(function () {
    // AI Chat
    Route::get('ai/chat', [ChatController::class, 'index'])->name('ai.chat.index');
    Route::get('ai/chat/attachments', [ChatAttachmentController::class, 'index'])->name('ai.chat.attachments.index');
    Route::post('ai/chat/attachments', [ChatAttachmentController::class, 'store'])->name('ai.chat.attachments.store');
    Route::get('ai/chat/attachments/{attachment}', [ChatAttachmentController::class, 'show'])->name('ai.chat.attachments.show');
    Route::delete('ai/chat/attachments/{attachment}', [ChatAttachmentController::class, 'destroy'])->name('ai.chat.attachments.destroy');
    Route::get('ai/sources', [SourcesController::class, 'index'])->name('ai.sources.index');
    Route::post('ai/chat/{thread}/sources', [SourcesController::class, 'attach'])->name('ai.chat.sources.store');
    Route::delete('ai/chat/{thread}/sources/{attachment}', [SourcesController::class, 'detach'])->name('ai.chat.sources.destroy');
    Route::get('ai/chat/{thread}', [ChatController::class, 'show'])->name('ai.chat.show');
    Route::patch('ai/chat/{thread}', [ChatController::class, 'update'])->name('ai.chat.update');
    Route::delete('ai/chat/{thread}', [ChatController::class, 'destroy'])->name('ai.chat.destroy');
    Route::post('ai/chat', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('ai.chat.send');
    Route::post('ai/chat/{thread}/regenerate', [ChatController::class, 'regenerate'])->middleware('throttle:30,1')->name('ai.chat.regenerate');
    Route::post('ai/chat/{thread}/edit', [ChatController::class, 'edit'])->middleware('throttle:30,1')->name('ai.chat.edit');
    Route::post('ai/chat/{thread}/approve', [ChatController::class, 'approve'])->middleware('throttle:30,1')->name('ai.chat.approve');
    Route::get('ai/models', [ChatController::class, 'models'])->name('ai.models');

    // AI Memory
    Route::get('ai/memory', [MemoryController::class, 'index'])->name('ai.memory.index');
    Route::post('ai/memory', [MemoryController::class, 'store'])->name('ai.memory.store');
    Route::patch('ai/memory/{memory}', [MemoryController::class, 'update'])->name('ai.memory.update');
    Route::delete('ai/memory/{memory}', [MemoryController::class, 'destroy'])->name('ai.memory.destroy');
    Route::post('ai/memory/{memory}/promote', [MemoryController::class, 'promote'])->name('ai.memory.promote');

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
    Route::post('ai/generate-task-description', [AiInsightController::class, 'generateTaskDescription'])->name('ai.generate-task-description');

    // AI Module Assistants: a thin wrapper of the chat surface per module.
    // Declared after every literal `ai/*` route (and constrained to the known
    // module keys) so none of them can be captured as a module.
    Route::get('ai/{module}', [ChatController::class, 'module'])
        ->where('module', implode('|', AiScope::moduleKeys()))
        ->name('ai.module');

    // Task board columns
    Route::post('task-board-columns', [TaskBoardColumnController::class, 'store'])->name('task-board-columns.store');
    Route::patch('task-board-columns/reorder', [TaskBoardColumnController::class, 'reorder'])->name('task-board-columns.reorder');
    Route::patch('task-board-columns/{column}', [TaskBoardColumnController::class, 'update'])->name('task-board-columns.update');
    Route::delete('task-board-columns/{column}', [TaskBoardColumnController::class, 'destroy'])->name('task-board-columns.destroy');

    // Gym Routes
    Route::prefix('gym')->group(function () {
        Route::post('workouts/{workout}/repeat', [WorkoutController::class, 'repeat']);
        Route::post('workouts/log-past', [WorkoutController::class, 'logPast']);
        Route::get('exercises/{exercise}/progression', [WorkoutController::class, 'progression']);
        Route::apiResource('exercises', ExerciseController::class);
        Route::apiResource('routines', RoutineController::class);
        Route::apiResource('workouts', WorkoutController::class);
        Route::post('workouts/{workout}/exercises', [WorkoutController::class, 'addExercise']);
        Route::post('workout-exercises/{workoutExercise}/sets', [WorkoutController::class, 'logSet']);
        Route::delete('workout-exercises/{workoutExercise}', [WorkoutController::class, 'removeExercise'])->name('workout-exercises.destroy');
        Route::delete('workout-sets/{workoutSet}', [WorkoutController::class, 'removeSet'])->name('workout-sets.destroy');
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

        Route::resource('projects.tasks', ProjectTaskController::class)->shallow()->except(['create', 'edit', 'show']);
        Route::patch('tasks/{task}/move', [ProjectTaskController::class, 'move'])->name('tasks.move');
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

    // Today — sistema de ejecución diaria
    Route::prefix('today')->name('today.')->group(function () {
        Route::get('/', [TodayController::class, 'index'])->name('index');
        Route::get('tomorrow', [TomorrowController::class, 'show'])->name('tomorrow');
        Route::post('tomorrow', [TomorrowController::class, 'store'])->name('tomorrow.store');
        Route::post('tomorrow/{item}/put-today', [TomorrowController::class, 'putToday'])->name('tomorrow.put-today');
        Route::patch('items/{item}', [TodayController::class, 'update'])->name('items.update');
        Route::post('items/{item}/release', [TodayController::class, 'release'])->name('items.release');
        Route::get('week', [WeekController::class, 'index'])->name('week');
        Route::get('week/search', [WeekController::class, 'search'])->name('week.search');
        Route::post('week', [WeekController::class, 'store'])->name('week.store');
        Route::delete('week/{task}', [WeekController::class, 'destroy'])->name('week.destroy');
        Route::get('queue', [QueueController::class, 'index'])->name('queue');
        Route::get('queue/search', [QueueController::class, 'search'])->name('queue.search');
        Route::post('queue', [QueueController::class, 'store'])->name('queue.store');
        Route::post('queue/next', [QueueController::class, 'next'])->name('queue.next');
        Route::patch('queue/reorder', [QueueController::class, 'reorder'])->name('queue.reorder');
        Route::delete('queue/{item}', [QueueController::class, 'destroy'])->name('queue.destroy');
        Route::get('archived', [ArchiveController::class, 'index'])->name('archived');
        Route::post('archived/restore', [ArchiveController::class, 'restore'])->name('archived.restore');
        Route::get('bulk-archive', [ArchiveBulkController::class, 'index'])->name('bulk-archive');
        Route::post('bulk-archive/preview', [ArchiveBulkController::class, 'preview'])->name('bulk-archive.preview');
        Route::post('bulk-archive/run', [ArchiveBulkController::class, 'run'])->name('bulk-archive.run');
        Route::post('bulk-archive/undo', [ArchiveBulkController::class, 'undo'])->name('bulk-archive.undo');
    });
});
