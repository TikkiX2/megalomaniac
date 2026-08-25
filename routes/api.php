<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CreditCardController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\CurrencyExchangeController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\ExchangeRateController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\FinanceStatisticsController;
use App\Http\Controllers\Api\V1\GroceryController;
use App\Http\Controllers\Api\V1\IncomeController;
use App\Http\Controllers\Api\V1\IncomeSourceController;
use App\Http\Controllers\Api\V1\NutritionController;
use App\Http\Controllers\Api\V1\PersonalProjectController;
use App\Http\Controllers\Api\V1\PersonalTaskController;
use App\Http\Controllers\Api\V1\ProjectCommentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ProjectTaskController;
use App\Http\Controllers\Api\V1\PurchaseCategoryController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\RoutineController;
use App\Http\Controllers\Api\V1\SavingsReserveController;
use App\Http\Controllers\Api\V1\SupplementController;
use App\Http\Controllers\Api\V1\WithdrawalCategoryController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use App\Http\Controllers\Api\V1\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

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

        // Finance - Statistics
        Route::get('finance/statistics', [FinanceStatisticsController::class, 'index']);

        // Finance - Purchases
        Route::get('finance/purchases', [PurchaseController::class, 'index']);
        Route::post('finance/purchases', [PurchaseController::class, 'store']);
        Route::get('finance/purchases/{purchase}', [PurchaseController::class, 'show']);
        Route::patch('finance/purchases/{purchase}', [PurchaseController::class, 'update']);
        Route::delete('finance/purchases/{purchase}', [PurchaseController::class, 'destroy']);

        // Finance - Income
        Route::get('finance/income', [IncomeController::class, 'index']);
        Route::post('finance/income', [IncomeController::class, 'store']);
        Route::get('finance/income/{income}', [IncomeController::class, 'show']);
        Route::patch('finance/income/{income}', [IncomeController::class, 'update']);
        Route::delete('finance/income/{income}', [IncomeController::class, 'destroy']);

        // Finance - Income Sources
        Route::get('finance/income-sources', [IncomeSourceController::class, 'index']);
        Route::post('finance/income-sources', [IncomeSourceController::class, 'store']);
        Route::get('finance/income-sources/{incomeSource}', [IncomeSourceController::class, 'show']);
        Route::patch('finance/income-sources/{incomeSource}', [IncomeSourceController::class, 'update']);
        Route::delete('finance/income-sources/{incomeSource}', [IncomeSourceController::class, 'destroy']);

        // Finance - Purchase Categories
        Route::get('finance/purchase-categories', [PurchaseCategoryController::class, 'index']);
        Route::post('finance/purchase-categories', [PurchaseCategoryController::class, 'store']);
        Route::get('finance/purchase-categories/{category}', [PurchaseCategoryController::class, 'show']);
        Route::patch('finance/purchase-categories/{category}', [PurchaseCategoryController::class, 'update']);
        Route::delete('finance/purchase-categories/{category}', [PurchaseCategoryController::class, 'destroy']);

        // Finance - Debts
        Route::get('finance/debts', [DebtController::class, 'index']);
        Route::post('finance/debts', [DebtController::class, 'store']);
        Route::get('finance/debts/{debt}', [DebtController::class, 'show']);
        Route::patch('finance/debts/{debt}', [DebtController::class, 'update']);
        Route::delete('finance/debts/{debt}', [DebtController::class, 'destroy']);
        Route::post('finance/debts/{debt}/payments', [DebtController::class, 'addPayment']);

        // Finance - Credit Cards
        Route::get('finance/credit-cards', [CreditCardController::class, 'index']);
        Route::post('finance/credit-cards', [CreditCardController::class, 'store']);
        Route::get('finance/credit-cards/{creditCard}', [CreditCardController::class, 'show']);
        Route::patch('finance/credit-cards/{creditCard}', [CreditCardController::class, 'update']);
        Route::delete('finance/credit-cards/{creditCard}', [CreditCardController::class, 'destroy']);

        // Finance - Currencies
        Route::get('finance/currencies', [CurrencyController::class, 'index']);
        Route::post('finance/currencies', [CurrencyController::class, 'store']);
        Route::get('finance/currencies/{currency}', [CurrencyController::class, 'show']);
        Route::patch('finance/currencies/{currency}', [CurrencyController::class, 'update']);
        Route::delete('finance/currencies/{currency}', [CurrencyController::class, 'destroy']);

        // Finance - Exchange Rates
        Route::get('finance/exchange-rates', [ExchangeRateController::class, 'index']);
        Route::post('finance/exchange-rates', [ExchangeRateController::class, 'store']);
        Route::get('finance/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'show']);
        Route::patch('finance/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'update']);
        Route::delete('finance/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy']);
        Route::post('finance/exchange-rates/convert', [ExchangeRateController::class, 'convert']);

        // Finance - Withdrawal Categories
        Route::get('finance/withdrawal-categories', [WithdrawalCategoryController::class, 'index']);
        Route::post('finance/withdrawal-categories', [WithdrawalCategoryController::class, 'store']);
        Route::get('finance/withdrawal-categories/{category}', [WithdrawalCategoryController::class, 'show']);
        Route::patch('finance/withdrawal-categories/{category}', [WithdrawalCategoryController::class, 'update']);
        Route::delete('finance/withdrawal-categories/{category}', [WithdrawalCategoryController::class, 'destroy']);

        // Finance - Withdrawals
        Route::get('finance/withdrawals', [WithdrawalController::class, 'index']);
        Route::post('finance/withdrawals', [WithdrawalController::class, 'store']);
        Route::get('finance/withdrawals/{withdrawal}', [WithdrawalController::class, 'show']);
        Route::patch('finance/withdrawals/{withdrawal}', [WithdrawalController::class, 'update']);
        Route::delete('finance/withdrawals/{withdrawal}', [WithdrawalController::class, 'destroy']);

        // Finance - Savings Reserves
        Route::get('finance/savings-reserves', [SavingsReserveController::class, 'index']);
        Route::post('finance/savings-reserves', [SavingsReserveController::class, 'store']);
        Route::get('finance/savings-reserves/{reserve}', [SavingsReserveController::class, 'show']);
        Route::patch('finance/savings-reserves/{reserve}', [SavingsReserveController::class, 'update']);
        Route::delete('finance/savings-reserves/{reserve}', [SavingsReserveController::class, 'destroy']);
        Route::post('finance/savings-reserves/{reserve}/deposit', [SavingsReserveController::class, 'deposit']);
        Route::post('finance/savings-reserves/{reserve}/withdraw', [SavingsReserveController::class, 'withdraw']);

        // Finance - Currency Exchanges
        Route::get('finance/currency-exchanges', [CurrencyExchangeController::class, 'index']);
        Route::post('finance/currency-exchanges', [CurrencyExchangeController::class, 'store']);
        Route::get('finance/currency-exchanges/{exchange}', [CurrencyExchangeController::class, 'show']);
        Route::patch('finance/currency-exchanges/{exchange}', [CurrencyExchangeController::class, 'update']);
        Route::delete('finance/currency-exchanges/{exchange}', [CurrencyExchangeController::class, 'destroy']);

        // Freelance - Clients
        Route::get('freelance/clients', [ClientController::class, 'index']);
        Route::post('freelance/clients', [ClientController::class, 'store']);
        Route::get('freelance/clients/{client}', [ClientController::class, 'show']);
        Route::patch('freelance/clients/{client}', [ClientController::class, 'update']);
        Route::delete('freelance/clients/{client}', [ClientController::class, 'destroy']);

        // Freelance - Projects
        Route::get('freelance/projects', [ProjectController::class, 'index']);
        Route::post('freelance/projects', [ProjectController::class, 'store']);
        Route::get('freelance/projects/{project}', [ProjectController::class, 'show']);
        Route::patch('freelance/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('freelance/projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('freelance/projects/{project}/payments', [ProjectController::class, 'addPayment']);

        // Freelance - Tasks
        Route::get('freelance/tasks', [ProjectTaskController::class, 'index']);
        Route::post('freelance/tasks', [ProjectTaskController::class, 'store']);
        Route::get('freelance/tasks/{task}', [ProjectTaskController::class, 'show']);
        Route::patch('freelance/tasks/{task}', [ProjectTaskController::class, 'update']);
        Route::delete('freelance/tasks/{task}', [ProjectTaskController::class, 'destroy']);

        // Freelance - Comments
        Route::get('freelance/projects/{project}/comments', [ProjectCommentController::class, 'index']);
        Route::post('freelance/projects/{project}/comments', [ProjectCommentController::class, 'store']);
        Route::patch('freelance/projects/{project}/comments/{comment}', [ProjectCommentController::class, 'update']);
        Route::delete('freelance/projects/{project}/comments/{comment}', [ProjectCommentController::class, 'destroy']);

        // Freelance - Quotes
        Route::get('freelance/quotes', [QuoteController::class, 'index']);
        Route::post('freelance/quotes', [QuoteController::class, 'store']);
        Route::get('freelance/quotes/{quote}', [QuoteController::class, 'show']);
        Route::patch('freelance/quotes/{quote}', [QuoteController::class, 'update']);
        Route::delete('freelance/quotes/{quote}', [QuoteController::class, 'destroy']);

        // Personal - Projects
        Route::get('personal/projects', [PersonalProjectController::class, 'index']);
        Route::post('personal/projects', [PersonalProjectController::class, 'store']);
        Route::get('personal/projects/{project}', [PersonalProjectController::class, 'show']);
        Route::patch('personal/projects/{project}', [PersonalProjectController::class, 'update']);
        Route::delete('personal/projects/{project}', [PersonalProjectController::class, 'destroy']);
        Route::post('personal/projects/{project}/milestones', [PersonalProjectController::class, 'storeMilestone']);

        // Personal - Tasks
        Route::get('personal/tasks', [PersonalTaskController::class, 'index']);
        Route::post('personal/tasks', [PersonalTaskController::class, 'store']);
        Route::get('personal/tasks/{task}', [PersonalTaskController::class, 'show']);
        Route::patch('personal/tasks/{task}', [PersonalTaskController::class, 'update']);
        Route::delete('personal/tasks/{task}', [PersonalTaskController::class, 'destroy']);
        Route::patch('personal/tasks/{task}/move', [PersonalTaskController::class, 'move']);
    });
});
