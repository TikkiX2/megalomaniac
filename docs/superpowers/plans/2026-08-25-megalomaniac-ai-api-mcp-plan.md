# Megalomaniac Pro — AI + API + MCP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update all dependencies, add REST API for all modules, implement AI assistant with streaming, and create MCP server for external AI tools.

**Architecture:** Laravel 12 + `laravel/ai` for AI agents + `laravel/mcp` for MCP server + Sanctum for auth. User provides own OpenAI-compatible API key + URL. Backend calls AI API with user context. Streaming via SSE.

**Tech Stack:** PHP 8.4, Laravel 12, `laravel/ai` 0.x, `laravel/mcp`, `laravel/sanctum`, React 19, Inertia v2, Tailwind v4, Pest 4

**Spec:** `docs/superpowers/specs/2026-08-25-megalomaniac-ai-api-mcp-design.md`

## Global Constraints

- PHP ^8.4, Laravel ^12.0
- All tests must pass: `php artisan test --compact`
- Types must pass: `npm run types`
- Lint must pass: `vendor/bin/pint --dirty`
- Wayfinder for all route references (no hardcoded URLs)
- Eloquent with casts() + eager loading, no DB::
- Form Requests for validation
- Tailwind v4 tokens (no hardcoded hex)
- Sanctum tokens for API + MCP auth (same token)

---

## Phase 1: Dependency Updates

### Task 1.1: Update PHP and Composer Dependencies

**Files:**
- Modify: `composer.json`
- Run: `composer update`

- [ ] **Step 1: Check current PHP version**

Run: `php -v`
Expected: PHP 8.2+

- [ ] **Step 2: Update composer.json require versions**

Update `composer.json` to require PHP ^8.4 and bump all package versions to latest compatible:

```json
{
    "require": {
        "php": "^8.4",
        "doctrine/dbal": "^4.4",
        "fiveam-code/laravel-notion-api": "^1.3",
        "inertiajs/inertia-laravel": "^2.0",
        "laravel/fortify": "^1.30",
        "laravel/framework": "^12.0",
        "laravel/tinker": "^2.10.1",
        "laravel/wayfinder": "^0.1.9",
        "spatie/laravel-medialibrary": "^11.18",
        "tecnickcom/tcpdf": "^6.10"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/boost": "^2.1",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.41",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "pestphp/pest": "^4.3",
        "pestphp/pest-plugin-laravel": "^4.0"
    }
}
```

- [ ] **Step 3: Run composer update**

Run: `composer update --no-interaction`
Expected: All packages updated, no errors

- [ ] **Step 4: Run tests to verify nothing broke**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 5: Run Pint to fix style**

Run: `vendor/bin/pint --dirty`
Expected: No errors

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: update composer dependencies to latest stable"
```

---

### Task 1.2: Update NPM Dependencies

**Files:**
- Modify: `package.json`
- Run: `npm update`

- [ ] **Step 1: Update package.json dependencies**

Bump all dependencies to latest compatible versions in `package.json`.

- [ ] **Step 2: Run npm update**

Run: `npm update --no-interaction`
Expected: All packages updated

- [ ] **Step 3: Run build to verify**

Run: `npm run build`
Expected: Build succeeds

- [ ] **Step 4: Run types check**

Run: `npm run types`
Expected: No type errors

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: update npm dependencies to latest stable"
```

---

### Task 1.3: Install New Packages

**Files:**
- Modify: `composer.json`
- Modify: `package.json`

- [ ] **Step 1: Install laravel/ai**

Run: `composer require laravel/ai`
Expected: Package installed

- [ ] **Step 2: Install laravel/mcp**

Run: `composer require laravel/mcp`
Expected: Package installed

- [ ] **Step 3: Install laravel/sanctum**

Run: `composer require laravel/sanctum`
Expected: Package installed (may already be included via Fortify)

- [ ] **Step 4: Publish AI SDK config and migrations**

Run: `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"`
Expected: `config/ai.php` and migration files published

- [ ] **Step 5: Publish MCP routes**

Run: `php artisan vendor:publish --tag=ai-routes`
Expected: `routes/ai.php` created

- [ ] **Step 6: Run migrations**

Run: `php artisan migrate`
Expected: `agent_conversations` and `agent_conversation_messages` tables created

- [ ] **Step 7: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 8: Commit**

```bash
git add composer.json composer.lock config/ai.php routes/ai.php database/migrations/
git commit -m "feat: install laravel/ai, laravel/mcp, laravel/sanctum"
```

---

## Phase 2: REST API

### Task 2.1: Setup API Infrastructure

**Files:**
- Create: `app/Http/Controllers/Api/V1/Controller.php`
- Create: `app/Http/Resources/WorkoutResource.php`
- Create: `app/Http/Requests/Api/StoreWorkoutRequest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create base API controller**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

class ApiController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
```

- [ ] **Step 2: Create WorkoutResource**

```php
<?php

namespace App\Http\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'routine_id' => $this->routine_id,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'notes' => $this->notes,
            'exercises' => WorkoutExerciseResource::collection($this->whenLoaded('exercises')),
            'routine' => new RoutineResource($this->whenLoaded('routine')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 3: Create StoreWorkoutRequest**

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'routine_id' => 'nullable|exists:routines,id',
            'started_at' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
```

- [ ] **Step 4: Add API route to routes/api.php**

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('workouts', \App\Http\Controllers\Api\V1\WorkoutController::class);
});
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=ApiWorkout`
Expected: Tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/ app/Http/Resources/ app/Http/Requests/Api/ routes/api.php
git commit -m "feat: setup API infrastructure with base controller, resources, requests"
```

---

### Task 2.2: Implement Fitness API

**Files:**
- Create: `app/Http/Controllers/Api/V1/WorkoutController.php`
- Create: `app/Http/Controllers/Api/V1/ExerciseController.php`
- Create: `app/Http/Controllers/Api/V1/RoutineController.php`
- Create: `app/Http/Resources/ExerciseResource.php`
- Create: `app/Http/Resources/RoutineResource.php`
- Create: `app/Http/Resources/WorkoutExerciseResource.php`
- Create: `app/Http/Resources/WorkoutSetResource.php`
- Create: `app/Http/Requests/Api/StoreExerciseRequest.php`
- Create: `app/Http/Requests/Api/StoreRoutineRequest.php`

- [ ] **Step 1: Create WorkoutController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWorkoutRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkoutController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workouts = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $request->user()->id)
            ->latest('started_at')
            ->paginate(20);

        return WorkoutResource::collection($workouts);
    }

    public function store(StoreWorkoutRequest $request): WorkoutResource
    {
        $workout = Workout::create([
            'user_id' => $request->user()->id,
            'routine_id' => $request->validated('routine_id'),
            'started_at' => $request->validated('started_at'),
            'notes' => $request->validated('notes'),
        ]);

        return new WorkoutResource($workout->load(['exercises.sets', 'routine']));
    }

    public function show(Workout $workout): WorkoutResource
    {
        $this->authorize('view', $workout);

        return new WorkoutResource($workout->load(['exercises.sets', 'routine']));
    }

    public function destroy(Workout $workout): JsonResponse
    {
        $this->authorize('delete', $workout);

        $workout->delete();

        return response()->json(['message' => 'Workout deleted']);
    }
}
```

- [ ] **Step 2: Create ExerciseController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExerciseController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $exercises = Exercise::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return ExerciseResource::collection($exercises);
    }

    public function store(StoreExerciseRequest $request): ExerciseResource
    {
        $exercise = Exercise::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'muscle_group' => $request->validated('muscle_group'),
            'equipment' => $request->validated('equipment'),
        ]);

        return new ExerciseResource($exercise);
    }
}
```

- [ ] **Step 3: Create RoutineController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutineController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routines = Routine::with('exercises.exercise')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return RoutineResource::collection($routines);
    }

    public function store(StoreRoutineRequest $request): RoutineResource
    {
        $routine = Routine::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        return new RoutineResource($routine);
    }
}
```

- [ ] **Step 4: Create remaining Resources (Exercise, Routine, WorkoutExercise, WorkoutSet)**

Follow same pattern as WorkoutResource.

- [ ] **Step 5: Create remaining Form Requests**

Follow same pattern as StoreWorkoutRequest.

- [ ] **Step 6: Add API routes**

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('exercises', \App\Http\Controllers\Api\V1\ExerciseController::class);
    Route::apiResource('routines', \App\Http\Controllers\Api\V1\RoutineController::class);
    Route::apiResource('workouts', \App\Http\Controllers\Api\V1\WorkoutController::class);
    Route::post('workouts/{workout}/exercises', [\App\Http\Controllers\Api\V1\WorkoutController::class, 'addExercise']);
    Route::post('workout-exercises/{workoutExercise}/sets', [\App\Http\Controllers\Api\V1\WorkoutController::class, 'logSet']);
});
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/V1/ app/Http/Resources/ app/Http/Requests/Api/ routes/api.php
git commit -m "feat: implement Fitness API (workouts, exercises, routines)"
```

---

### Task 2.3: Implement Nutrition + Supplement + Grocery API

**Files:**
- Create: `app/Http/Controllers/Api/V1/NutritionController.php`
- Create: `app/Http/Controllers/Api/V1/SupplementController.php`
- Create: `app/Http/Controllers/Api/V1/GroceryController.php`
- Create: `app/Http/Resources/*Resource.php` (for each model)
- Create: `app/Http/Requests/Api/*Request.php` (for each action)

- [ ] **Step 1: Create NutritionController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\MealLogResource;
use App\Http\Resources\FoodResource;
use App\Models\Food;
use App\Models\MealLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NutritionController extends ApiController
{
    public function searchFoods(Request $request): AnonymousResourceCollection
    {
        $foods = Food::where('user_id', $request->user()->id)
            ->where('name', 'like', '%' . $request->q . '%')
            ->limit(20)
            ->get();

        return FoodResource::collection($foods);
    }

    public function getDailyLog(Request $request): MealLogResource
    {
        $log = MealLog::with('items.food')
            ->where('user_id', $request->user()->id)
            ->where('date', $request->date ?? now()->toDateString())
            ->firstOrCreate([
                'user_id' => $request->user()->id,
                'date' => $request->date ?? now()->toDateString(),
            ]);

        return new MealLogResource($log);
    }
}
```

- [ ] **Step 2: Create SupplementController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SupplementResource;
use App\Models\Supplement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplementController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $supplements = Supplement::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return SupplementResource::collection($supplements);
    }
}
```

- [ ] **Step 3: Create GroceryController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\GroceryItemResource;
use App\Models\GroceryItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroceryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = GroceryItem::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return GroceryItemResource::collection($items);
    }

    public function consume(GroceryItem $item, Request $request)
    {
        $this->authorize('update', $item);

        $item->decrement('quantity', $request->quantity ?? 1);

        return new GroceryItemResource($item->fresh());
    }
}
```

- [ ] **Step 4: Create Resources and Requests for each model**

Follow same pattern as Phase 2.2.

- [ ] **Step 5: Add API routes**

```php
Route::middleware('auth:sanctum')->group(function () {
    // Nutrition
    Route::get('foods/search', [\App\Http\Controllers\Api\V1\NutritionController::class, 'searchFoods']);
    Route::get('nutrition/logs', [\App\Http\Controllers\Api\V1\NutritionController::class, 'getDailyLog']);
    Route::post('nutrition/logs/items', [\App\Http\Controllers\Api\V1\NutritionController::class, 'storeMealItem']);

    // Supplements
    Route::apiResource('supplements', \App\Http\Controllers\Api\V1\SupplementController::class);
    Route::post('supplements/{supplement}/log', [\App\Http\Controllers\Api\V1\SupplementController::class, 'logIntake']);

    // Grocery
    Route::apiResource('grocery/items', \App\Http\Controllers\Api\V1\GroceryController::class);
    Route::post('grocery/{item}/consume', [\App\Http\Controllers\Api\V1\GroceryController::class, 'consume']);
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/ app/Http/Resources/ app/Http/Requests/Api/
git commit -m "feat: implement Nutrition, Supplement, Grocery API"
```

---

### Task 2.4: Implement Finance API

**Files:**
- Create: `app/Http/Controllers/Api/V1/PurchaseController.php`
- Create: `app/Http/Controllers/Api/V1/IncomeController.php`
- Create: `app/Http/Controllers/Api/V1/DebtController.php`
- Create: `app/Http/Controllers/Api/V1/CreditCardController.php`
- Create: `app/Http/Controllers/Api/V1/CurrencyController.php`
- Create: `app/Http/Controllers/Api/V1/ExchangeRateController.php`
- Create: `app/Http/Controllers/Api/V1/IncomeSourceController.php`
- Create: `app/Http/Controllers/Api/V1/PurchaseCategoryController.php`
- Create: `app/Http/Controllers/Api/V1/WithdrawalController.php`
- Create: `app/Http/Controllers/Api/V1/SavingsReserveController.php`
- Create: `app/Http/Controllers/Api/V1/CurrencyExchangeController.php`
- Create: `app/Http/Controllers/Api/V1/FinanceStatisticsController.php`
- Create: `app/Http/Resources/*Resource.php` (14 resources)
- Create: `app/Http/Requests/Api/*Request.php` (per action)

- [ ] **Step 1: Create PurchaseController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $purchases = Purchase::with(['category', 'currency'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return PurchaseResource::collection($purchases);
    }

    public function store(StorePurchaseRequest $request): PurchaseResource
    {
        $purchase = Purchase::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'amount' => $request->validated('amount'),
            'currency_id' => $request->validated('currency_id'),
            'category_id' => $request->validated('category_id'),
            'date' => $request->validated('date'),
            'notes' => $request->validated('notes'),
        ]);

        return new PurchaseResource($purchase->load(['category', 'currency']));
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        $this->authorize('view', $purchase);
        return new PurchaseResource($purchase->load(['category', 'currency']));
    }

    public function update(StorePurchaseRequest $request, Purchase $purchase): PurchaseResource
    {
        $this->authorize('update', $purchase);
        $purchase->update($request->validated());
        return new PurchaseResource($purchase->fresh()->load(['category', 'currency']));
    }

    public function destroy(Purchase $purchase)
    {
        $this->authorize('delete', $purchase);
        $purchase->delete();
        return response()->json(['message' => 'Purchase deleted']);
    }
}
```

- [ ] **Step 2: Create remaining Finance Controllers (13 more)**

Follow same pattern: index (with eager loading), store, show, update, destroy. Each controller filters by `$request->user()->id`.

- [ ] **Step 3: Create all Finance Resources**

Follow same pattern as WorkoutResource — include all relevant relationships.

- [ ] **Step 4: Create all Finance Form Requests**

Follow same pattern as StoreWorkoutRequest.

- [ ] **Step 5: Add Finance API routes**

```php
Route::middleware('auth:sanctum')->prefix('finance')->group(function () {
    Route::get('dashboard', [\App\Http\Controllers\Api\V1\FinanceStatisticsController::class, 'index']);
    Route::apiResource('purchases', \App\Http\Controllers\Api\V1\PurchaseController::class);
    Route::apiResource('incomes', \App\Http\Controllers\Api\V1\IncomeController::class);
    Route::apiResource('debts', \App\Http\Controllers\Api\V1\DebtController::class);
    Route::post('debts/{debt}/payments', [\App\Http\Controllers\Api\V1\DebtController::class, 'addPayment']);
    Route::apiResource('credit-cards', \App\Http\Controllers\Api\V1\CreditCardController::class);
    Route::apiResource('currencies', \App\Http\Controllers\Api\V1\CurrencyController::class);
    Route::apiResource('exchange-rates', \App\Http\Controllers\Api\V1\ExchangeRateController::class);
    Route::post('exchange-rates/convert', [\App\Http\Controllers\Api\V1\ExchangeRateController::class, 'convert']);
    Route::apiResource('income-sources', \App\Http\Controllers\Api\V1\IncomeSourceController::class);
    Route::apiResource('categories', \App\Http\Controllers\Api\V1\PurchaseCategoryController::class);
    Route::apiResource('withdrawals', \App\Http\Controllers\Api\V1\WithdrawalController::class);
    Route::apiResource('savings-reserves', \App\Http\Controllers\Api\V1\SavingsReserveController::class);
    Route::post('savings-reserves/{savings_reserve}/deposit', [\App\Http\Controllers\Api\V1\SavingsReserveController::class, 'deposit']);
    Route::post('savings-reserves/{savings_reserve}/withdraw', [\App\Http\Controllers\Api\V1\SavingsReserveController::class, 'withdraw']);
    Route::apiResource('currency-exchanges', \App\Http\Controllers\Api\V1\CurrencyExchangeController::class);
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/V1/ app/Http/Resources/ app/Http/Requests/Api/
git commit -m "feat: implement Finance API (14 resources)"
```

---

### Task 2.5: Implement Freelance + Personal API

**Files:**
- Create: `app/Http/Controllers/Api/V1/ClientController.php`
- Create: `app/Http/Controllers/Api/V1/ProjectController.php`
- Create: `app/Http/Controllers/Api/V1/ProjectTaskController.php`
- Create: `app/Http/Controllers/Api/V1/ProjectCommentController.php`
- Create: `app/Http/Controllers/Api/V1/QuoteController.php`
- Create: `app/Http/Controllers/Api/V1/PersonalProjectController.php`
- Create: `app/Http/Controllers/Api/V1/PersonalTaskController.php`
- Create: `app/Http/Resources/*Resource.php`
- Create: `app/Http/Requests/Api/*Request.php`

- [ ] **Step 1: Create Freelance Controllers**

Follow same pattern as Phase 2.4 — index with eager loading, store, show, update, destroy. Filter by user_id.

- [ ] **Step 2: Create Personal Controllers**

Same pattern for Personal projects and tasks.

- [ ] **Step 3: Create Resources and Requests**

Follow established patterns.

- [ ] **Step 4: Add API routes**

```php
Route::middleware('auth:sanctum')->prefix('freelance')->group(function () {
    Route::apiResource('clients', \App\Http\Controllers\Api\V1\ClientController::class);
    Route::apiResource('projects', \App\Http\Controllers\Api\V1\ProjectController::class);
    Route::post('projects/{project}/payments', [\App\Http\Controllers\Api\V1\ProjectController::class, 'addPayment']);
    Route::get('projects/{project}/comments', [\App\Http\Controllers\Api\V1\ProjectCommentController::class, 'index']);
    Route::post('projects/{project}/comments', [\App\Http\Controllers\Api\V1\ProjectCommentController::class, 'store']);
    Route::apiResource('quotes', \App\Http\Controllers\Api\V1\QuoteController::class);
    Route::apiResource('projects.tasks', \App\Http\Controllers\Api\V1\ProjectTaskController::class)->shallow();
});

Route::middleware('auth:sanctum')->prefix('personal')->group(function () {
    Route::apiResource('projects', \App\Http\Controllers\Api\V1\PersonalProjectController::class);
    Route::apiResource('tasks', \App\Http\Controllers\Api\V1\PersonalTaskController::class)->except(['create', 'edit']);
    Route::patch('tasks/{task}/move', [\App\Http\Controllers\Api\V1\PersonalTaskController::class, 'move']);
});
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/ app/Http/Resources/ app/Http/Requests/Api/
git commit -m "feat: implement Freelance + Personal API"
```

---

### Task 2.6: API Authentication + Sanctum Setup

**Files:**
- Create: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `config/sanctum.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Create AuthController**

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
```

- [ ] **Step 2: Add auth routes**

```php
Route::post('auth/login', [\App\Http\Controllers\Api\V1\AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
    Route::get('auth/me', [\App\Http\Controllers\Api\V1\AuthController::class, 'me']);
});
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/V1/AuthController.php routes/api.php config/sanctum.php
git commit -m "feat: add API authentication with Sanctum"
```

---

## Phase 3: AI Features + In-App Assistant

### Task 3.1: Setup AI Configuration

**Files:**
- Modify: `config/ai.php`
- Create: `database/migrations/xxxx_add_ai_settings_to_users_table.php`
- Modify: `app/Models/User.php`
- Create: `resources/js/pages/settings/ai.tsx`

- [ ] **Step 1: Create migration for AI settings**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('ai_provider_url')->nullable()->after('email');
            $table->text('ai_provider_key')->nullable()->after('ai_provider_url');
            $table->string('ai_model')->default('gpt-4')->after('ai_provider_key');
            $table->boolean('ai_enabled')->default(false)->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ai_provider_url', 'ai_provider_key', 'ai_model', 'ai_enabled']);
        });
    }
};
```

- [ ] **Step 2: Add casts to User model**

```php
protected function casts(): array
{
    return [
        // ... existing casts
        'ai_provider_key' => 'encrypted',
        'ai_enabled' => 'boolean',
    ];
}
```

- [ ] **Step 3: Configure ai.php for user-provided keys**

```php
'providers' => [
    'user' => [
        'driver' => 'openai-compatible',
        'url' => fn () => auth()->user()?->ai_provider_url ?? config('services.ai.default_url'),
        'key' => fn () => auth()->user()?->ai_provider_key ?? config('services.ai.default_key'),
    ],
],
```

- [ ] **Step 4: Create AI Settings page**

Create `resources/js/pages/settings/ai.tsx` with form for:
- AI Provider URL
- API Key (masked input)
- Model name
- Enable/Disable toggle

- [ ] **Step 5: Add settings route**

```php
Route::get('ai', [Settings\AiSettingsController::class, 'edit'])->name('settings.ai');
Route::put('ai', [Settings\AiSettingsController::class, 'update']);
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add config/ai.php database/migrations/ app/Models/User.php resources/js/pages/settings/ai.tsx
git commit -m "feat: add AI configuration with user-provided API keys"
```

---

### Task 3.2: Create AI Agent + Tools

**Files:**
- Create: `app/Ai/Agents/MegalomaniacAgent.php`
- Create: `app/Ai/Tools/WorkoutQueryTool.php`
- Create: `app/Ai/Tools/FinanceQueryTool.php`
- Create: `app/Ai/Tools/NutritionQueryTool.php`
- Create: `app/Ai/Tools/GroceryQueryTool.php`
- Create: `app/Ai/Tools/ActionTool.php`

- [ ] **Step 1: Create MegalomaniacAgent**

```php
<?php

namespace App\Ai\Agents;

use App\Ai\Tools\WorkoutQueryTool;
use App\Ai\Tools\FinanceQueryTool;
use App\Ai\Tools\NutritionQueryTool;
use App\Ai\Tools\GroceryQueryTool;
use App\Ai\Tools\ActionTool;
use App\Models\User;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class MegalomaniacAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public User $user) {}

    public function instructions(): string
    {
        return <<<'EOF'
You are Megalomaniac AI, a personal fitness, finance, and freelance assistant.

You help the user with:
- Fitness: workout planning, exercise recommendations, progress tracking
- Finance: budgeting, expense tracking, income management
- Nutrition: meal planning, macro tracking, food suggestions
- Grocery: shopping lists, inventory management
- Freelance: project management, client communication

You have access to the user's data through tools. Always use tools to fetch
real data before making recommendations. Be concise and actionable.

When the user asks to perform an action (log a workout, add a purchase, etc.),
use the ActionTool to create the record.
EOF;
    }

    public function tools(): iterable
    {
        return [
            new WorkoutQueryTool($this->user),
            new FinanceQueryTool($this->user),
            new NutritionQueryTool($this->user),
            new GroceryQueryTool($this->user),
            new ActionTool($this->user),
        ];
    }
}
```

- [ ] **Step 2: Create WorkoutQueryTool**

```php
<?php

namespace App\Ai\Tools;

use App\Models\Workout;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class WorkoutQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s workout history, exercises, and routines.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $this->user->id);

        if ($request->has('days')) {
            $query->where('started_at', '>=', now()->subDays($request->get('days')));
        }

        if ($request->has('exercise')) {
            $query->whereHas('exercises', fn ($q) => $q->where('name', 'like', '%' . $request->get('exercise') . '%'));
        }

        $workouts = $query->latest('started_at')->limit(10)->get();

        return json_encode($workouts->toArray(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()->description('Number of days to look back (default: 30)')->default(30),
            'exercise' => $schema->string()->description('Filter by exercise name'),
        ];
    }
}
```

- [ ] **Step 3: Create remaining Tools (FinanceQuery, NutritionQuery, GroceryQuery, Action)**

Follow same pattern — each tool queries the user's data with relevant filters.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Ai/
git commit -m "feat: create AI agent with fitness, finance, nutrition, grocery tools"
```

---

### Task 3.3: In-App Chat Interface

**Files:**
- Create: `app/Http/Controllers/AiChatController.php`
- Create: `resources/js/components/ai/ChatPanel.tsx`
- Create: `resources/js/components/ai/MessageBubble.tsx`
- Modify: `resources/js/layouts/main-layout.tsx`

- [ ] **Step 1: Create AiChatController**

```php
<?php

namespace App\Http\Controllers;

use App\Ai\Agents\MegalomaniacAgent;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:4000',
            'conversation_id' => 'nullable|string',
        ]);

        $agent = MegalomaniacAgent::make(user: $request->user());

        if ($request->conversation_id) {
            return $agent
                ->continue($request->conversation_id, as: $request->user())
                ->stream($request->input('message'));
        }

        return $agent
            ->forUser($request->user())
            ->stream($request->input('message'));
    }

    public function conversations(Request $request)
    {
        $conversations = $request->user()
            ->conversations()
            ->latest('updated_at')
            ->paginate(20);

        return response()->json($conversations);
    }
}
```

- [ ] **Step 2: Add chat route**

```php
Route::post('ai/chat', [\App\Http\Controllers\AiChatController::class, 'chat'])->name('ai.chat');
Route::get('ai/conversations', [\App\Http\Controllers\AiChatController::class, 'conversations'])->name('ai.conversations');
```

- [ ] **Step 3: Create ChatPanel component**

```tsx
// resources/js/components/ai/ChatPanel.tsx
import { useState, useRef, useEffect } from 'react';
import { MessageBubble } from './MessageBubble';

interface Message {
    role: 'user' | 'assistant';
    content: string;
}

export function ChatPanel() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [isStreaming, setIsStreaming] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    const sendMessage = async () => {
        if (!input.trim() || isStreaming) return;

        const userMessage = { role: 'user' as const, content: input };
        setMessages(prev => [...prev, userMessage]);
        setInput('');
        setIsStreaming(true);

        try {
            const response = await fetch('/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'text/event-stream',
                },
                body: JSON.stringify({ message: input }),
            });

            const reader = response.body?.getReader();
            const decoder = new TextDecoder();
            let assistantMessage = '';

            setMessages(prev => [...prev, { role: 'assistant', content: '' }]);

            while (reader) {
                const { done, value } = await reader.read();
                if (done) break;

                const chunk = decoder.decode(value);
                assistantMessage += chunk;

                setMessages(prev => {
                    const updated = [...prev];
                    updated[updated.length - 1] = { role: 'assistant', content: assistantMessage };
                    return updated;
                });
            }
        } catch (error) {
            console.error('Chat error:', error);
        } finally {
            setIsStreaming(false);
        }
    };

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    return (
        <div className="flex flex-col h-full">
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                {messages.map((msg, i) => (
                    <MessageBubble key={i} message={msg} />
                ))}
                <div ref={messagesEndRef} />
            </div>
            <div className="border-t p-4">
                <div className="flex gap-2">
                    <input
                        type="text"
                        value={input}
                        onChange={e => setInput(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && sendMessage()}
                        placeholder="Ask about your workouts, finances, nutrition..."
                        className="flex-1 bg-background border border-border rounded-lg px-4 py-2 text-foreground"
                        disabled={isStreaming}
                    />
                    <button
                        onClick={sendMessage}
                        disabled={isStreaming || !input.trim()}
                        className="bg-primary text-white px-4 py-2 rounded-lg disabled:opacity-50"
                    >
                        {isStreaming ? 'Thinking...' : 'Send'}
                    </button>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Create MessageBubble component**

```tsx
// resources/js/components/ai/MessageBubble.tsx
interface Message {
    role: 'user' | 'assistant';
    content: string;
}

export function MessageBubble({ message }: { message: Message }) {
    const isUser = message.role === 'user';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div className={`max-w-[80%] rounded-lg px-4 py-2 ${
                isUser
                    ? 'bg-primary text-white'
                    : 'bg-card text-foreground border border-border'
            }`}>
                <p className="whitespace-pre-wrap">{message.content}</p>
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Add ChatPanel to main layout**

Add a collapsible chat panel to the sidebar or as a floating button in `main-layout.tsx`.

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact && npm run types`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AiChatController.php resources/js/components/ai/ resources/js/layouts/main-layout.tsx
git commit -m "feat: add in-app AI chat interface with streaming"
```

---

### Task 3.4: AI Insights + Smart Suggestions

**Files:**
- Create: `app/AI/Services/InsightService.php`
- Create: `app/AI/Services/SuggestionService.php`
- Create: `app/Jobs/GenerateInsightsJob.php`

- [ ] **Step 1: Create InsightService**

```php
<?php

namespace App\AI\Services;

use App\Models\User;
use Laravel\Ai\Facades\Ai;

class InsightService
{
    public function generateWorkoutInsights(User $user): array
    {
        $recentWorkouts = $user->workouts()
            ->with('exercises.sets')
            ->where('started_at', '>=', now()->subDays(30))
            ->get();

        $response = Ai::user()->agent(\App\Ai\Agents\MegalomaniacAgent::class, $user)
            ->structured([
                'trend' => 'string',
                'recommendation' => 'string',
                'pr_alerts' => 'array',
            ])
            ->prompt("Analyze this workout data and provide insights: " . $recentWorkouts->toJson());

        return $response->toArray();
    }

    public function generateFinanceInsights(User $user): array
    {
        // Similar pattern for finance data
    }
}
```

- [ ] **Step 2: Create SuggestionService**

```php
<?php

namespace App\AI\Services;

use App\Models\User;
use App\Models\AgentSuggestion;

class SuggestionService
{
    public function generateSuggestions(User $user): void
    {
        // Workout suggestions
        if ($this->shouldSuggestWorkout($user)) {
            AgentSuggestion::create([
                'user_id' => $user->id,
                'type' => 'workout',
                'title' => 'Time for a workout!',
                'content' => 'You haven\'t worked out in 3 days. Based on your routine, today is chest day.',
            ]);
        }

        // Finance suggestions
        if ($this->shouldSuggestBudgetReview($user)) {
            AgentSuggestion::create([
                'user_id' => $user->id,
                'type' => 'finance',
                'title' => 'Budget review',
                'content' => 'You\'ve spent 80% of your food budget this month.',
            ]);
        }
    }

    private function shouldSuggestWorkout(User $user): bool
    {
        return $user->workouts()
            ->where('started_at', '>=', now()->subDays(3))
            ->count() === 0;
    }

    private function shouldSuggestBudgetReview(User $user): bool
    {
        // Check spending vs budget
        return false;
    }
}
```

- [ ] **Step 3: Create GenerateInsightsJob**

```php
<?php

namespace App\Jobs;

use App\AI\Services\SuggestionService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class GenerateInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public User $user) {}

    public function handle(SuggestionService $suggestionService): void
    {
        $suggestionService->generateSuggestions($this->user);
    }
}
```

- [ ] **Step 4: Schedule the job**

```php
// app/Console/Kernel.php or routes/console.php
Schedule::job(new GenerateInsightsJob($user))->daily();
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add app/AI/ app/Jobs/
git commit -m "feat: add AI insights and smart suggestions engine"
```

---

## Phase 4: Proactive AI Agents

### Task 4.1: Agent Suggestion System

**Files:**
- Create: `database/migrations/xxxx_create_agent_suggestions_table.php`
- Create: `app/Models/AgentSuggestion.php`
- Create: `app/Http/Controllers/AgentSuggestionController.php`
- Create: `resources/js/components/ai/SuggestionCard.tsx`

- [ ] **Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // workout, finance, nutrition, freelance
            $table->string('title');
            $table->text('content');
            $table->json('data')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_suggestions');
    }
};
```

- [ ] **Step 2: Create AgentSuggestion model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSuggestion extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'content', 'data', 'dismissed_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('dismissed_at');
    }
}
```

- [ ] **Step 3: Create AgentSuggestionController**

```php
<?php

namespace App\Http\Controllers;

use App\Models\AgentSuggestion;
use Illuminate\Http\Request;

class AgentSuggestionController extends Controller
{
    public function index(Request $request)
    {
        $suggestions = AgentSuggestion::where('user_id', $request->user()->id)
            ->active()
            ->latest()
            ->get();

        return response()->json($suggestions);
    }

    public function dismiss(AgentSuggestion $suggestion)
    {
        $this->authorize('update', $suggestion);

        $suggestion->update(['dismissed_at' => now()]);

        return response()->json(['message' => 'Suggestion dismissed']);
    }
}
```

- [ ] **Step 4: Create SuggestionCard component**

```tsx
// resources/js/components/ai/SuggestionCard.tsx
interface Suggestion {
    id: number;
    type: string;
    title: string;
    content: string;
    data?: Record<string, unknown>;
}

export function SuggestionCard({ suggestion, onDismiss }: { suggestion: Suggestion; onDismiss: (id: number) => void }) {
    return (
        <div className="bg-card border border-border rounded-lg p-4">
            <div className="flex justify-between items-start">
                <div>
                    <span className="text-xs text-muted-foreground uppercase">{suggestion.type}</span>
                    <h3 className="font-semibold text-foreground mt-1">{suggestion.title}</h3>
                    <p className="text-sm text-muted-foreground mt-1">{suggestion.content}</p>
                </div>
                <button
                    onClick={() => onDismiss(suggestion.id)}
                    className="text-muted-foreground hover:text-foreground"
                >
                    Dismiss
                </button>
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Add routes**

```php
Route::get('ai/suggestions', [\App\Http\Controllers\AgentSuggestionController::class, 'index']);
Route::post('ai/suggestions/{suggestion}/dismiss', [\App\Http\Controllers\AgentSuggestionController::class, 'dismiss']);
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 7: Commit**

```bash
git add database/migrations/ app/Models/AgentSuggestion.php app/Http/Controllers/AgentSuggestionController.php resources/js/components/ai/SuggestionCard.tsx
git commit -m "feat: add agent suggestion system with dismiss functionality"
```

---

## Phase 5: MCP Server

### Task 5.1: Setup MCP Server

**Files:**
- Create: `app/Mcp/Servers/MegalomaniacServer.php`
- Modify: `routes/ai.php`

- [ ] **Step 1: Create MegalomaniacServer**

```php
<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\WorkoutReadTool;
use App\Mcp\Tools\WorkoutWriteTool;
use App\Mcp\Tools\FinanceReadTool;
use App\Mcp\Tools\FinanceWriteTool;
use App\Mcp\Tools\NutritionReadTool;
use App\Mcp\Tools\NutritionWriteTool;
use App\Mcp\Tools\GroceryReadTool;
use App\Mcp\Tools\GroceryWriteTool;
use App\Mcp\Tools\FreelanceReadTool;
use App\Mcp\Tools\FreelanceWriteTool;
use App\Mcp\Resources\UserProfileResource;
use App\Mcp\Resources\WorkoutHistoryResource;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Megalomaniac')]
#[Version('1.0.0')]
#[Instructions('Megalomaniac Pro fitness, finance, and freelance assistant. Provides read/write access to workouts, nutrition, finances, and freelance projects.')]
class MegalomaniacServer extends Server
{
    protected array $tools = [
        WorkoutReadTool::class,
        WorkoutWriteTool::class,
        FinanceReadTool::class,
        FinanceWriteTool::class,
        NutritionReadTool::class,
        NutritionWriteTool::class,
        GroceryReadTool::class,
        GroceryWriteTool::class,
        FreelanceReadTool::class,
        FreelanceWriteTool::class,
    ];

    protected array $resources = [
        UserProfileResource::class,
        WorkoutHistoryResource::class,
    ];
}
```

- [ ] **Step 2: Register MCP server in routes/ai.php**

```php
<?php

use App\Mcp\Servers\MegalomaniacServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/megalomaniac', MegalomaniacServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add app/Mcp/ routes/ai.php
git commit -m "feat: setup MCP server with MegalomaniacServer"
```

---

### Task 5.2: Implement MCP Read Tools

**Files:**
- Create: `app/Mcp/Tools/WorkoutReadTool.php`
- Create: `app/Mcp/Tools/FinanceReadTool.php`
- Create: `app/Mcp/Tools/NutritionReadTool.php`
- Create: `app/Mcp/Tools/GroceryReadTool.php`
- Create: `app/Mcp/Tools/FreelanceReadTool.php`

- [ ] **Step 1: Create WorkoutReadTool**

```php
<?php

namespace App\Mcp\Tools;

use App\Models\Workout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('workout-read')]
#[Description('Query workout history, exercises, and routines. Returns workout data with exercises and sets.')]
#[IsReadOnly]
class WorkoutReadTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required');
        }

        $query = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $user->id);

        if ($request->has('days')) {
            $query->where('started_at', '>=', now()->subDays($request->get('days')));
        }

        if ($request->has('limit')) {
            $query->limit($request->get('limit'));
        } else {
            $query->limit(10);
        }

        $workouts = $query->latest('started_at')->get();

        return Response::structured($workouts->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->description('Number of days to look back (default: 30)')
                ->default(30),
            'limit' => $schema->integer()
                ->description('Maximum number of workouts to return (default: 10)')
                ->default(10),
        ];
    }
}
```

- [ ] **Step 2: Create FinanceReadTool**

```php
<?php

namespace App\Mcp\Tools;

use App\Models\Purchase;
use App\Models\Income;
use App\Models\Debt;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('finance-read')]
#[Description('Query financial data: purchases, incomes, debts, credit cards, currencies.')]
#[IsReadOnly]
class FinanceReadTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required');
        }

        $type = $request->get('type', 'purchases');
        $days = $request->get('days', 30);

        $query = match ($type) {
            'purchases' => Purchase::with(['category', 'currency'])->where('user_id', $user->id),
            'incomes' => Income::with(['source', 'currency'])->where('user_id', $user->id),
            'debts' => Debt::with('payments')->where('user_id', $user->id),
            default => Purchase::with(['category', 'currency'])->where('user_id', $user->id),
        };

        $items = $query->where('date', '>=', now()->subDays($days))
            ->latest()
            ->limit(20)
            ->get();

        return Response::structured($items->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(['purchases', 'incomes', 'debts', 'credit-cards'])
                ->description('Type of financial data to query')
                ->default('purchases'),
            'days' => $schema->integer()
                ->description('Number of days to look back (default: 30)')
                ->default(30),
        ];
    }
}
```

- [ ] **Step 3: Create remaining Read Tools (Nutrition, Grocery, Freelance)**

Follow same pattern — each tool queries the user's data with relevant filters and returns structured data.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Mcp/Tools/
git commit -m "feat: implement MCP read tools for all modules"
```

---

### Task 5.3: Implement MCP Write Tools

**Files:**
- Create: `app/Mcp/Tools/WorkoutWriteTool.php`
- Create: `app/Mcp/Tools/FinanceWriteTool.php`
- Create: `app/Mcp/Tools/NutritionWriteTool.php`
- Create: `app/Mcp/Tools/GroceryWriteTool.php`
- Create: `app/Mcp/Tools/FreelanceWriteTool.php`

- [ ] **Step 1: Create WorkoutWriteTool**

```php
<?php

namespace App\Mcp\Tools;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('workout-write')]
#[Description('Create workouts, add exercises, and log sets.')]
class WorkoutWriteTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required');
        }

        $action = $request->get('action');

        return match ($action) {
            'create_workout' => $this->createWorkout($user, $request),
            'add_exercise' => $this->addExercise($user, $request),
            'log_set' => $this->logSet($user, $request),
            default => Response::error('Invalid action. Use: create_workout, add_exercise, log_set'),
        };
    }

    private function createWorkout($user, Request $request): Response
    {
        $validated = $request->validate([
            'routine_id' => 'nullable|exists:routines,id',
            'started_at' => 'required|date',
        ]);

        $workout = Workout::create([
            'user_id' => $user->id,
            'routine_id' => $validated['routine_id'] ?? null,
            'started_at' => $validated['started_at'],
        ]);

        return Response::structured($workout->toArray());
    }

    private function addExercise($user, Request $request): Response
    {
        $validated = $request->validate([
            'workout_id' => 'required|exists:workouts,id',
            'exercise_id' => 'required|exists:exercises,id',
        ]);

        $workout = Workout::where('user_id', $user->id)->findOrFail($validated['workout_id']);

        $exercise = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'exercise_id' => $validated['exercise_id'],
        ]);

        return Response::structured($exercise->toArray());
    }

    private function logSet($user, Request $request): Response
    {
        $validated = $request->validate([
            'workout_exercise_id' => 'required|exists:workout_exercises,id',
            'weight' => 'required|numeric|min:0',
            'reps' => 'required|integer|min:1',
            'rpe' => 'nullable|numeric|min:1|max:10',
        ]);

        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($q) => $q->where('user_id', $user->id))
            ->findOrFail($validated['workout_exercise_id']);

        $set = WorkoutSet::create([
            'workout_exercise_id' => $workoutExercise->id,
            'weight' => $validated['weight'],
            'reps' => $validated['reps'],
            'rpe' => $validated['rpe'] ?? null,
        ]);

        return Response::structured($set->toArray());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_workout', 'add_exercise', 'log_set'])
                ->description('Action to perform')
                ->required(),
            'routine_id' => $schema->integer()->description('Routine ID (for create_workout)'),
            'started_at' => $schema->string()->description('Workout start time (ISO 8601) (for create_workout)'),
            'workout_id' => $schema->integer()->description('Workout ID (for add_exercise)'),
            'exercise_id' => $schema->integer()->description('Exercise ID (for add_exercise)'),
            'workout_exercise_id' => $schema->integer()->description('Workout Exercise ID (for log_set)'),
            'weight' => $schema->number()->description('Weight in kg/lbs (for log_set)'),
            'reps' => $schema->integer()->description('Number of reps (for log_set)'),
            'rpe' => $schema->number()->description('Rate of perceived exertion 1-10 (for log_set)'),
        ];
    }
}
```

- [ ] **Step 2: Create remaining Write Tools (Finance, Nutrition, Grocery, Freelance)**

Follow same pattern — each tool handles create/update/delete actions for its module.

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add app/Mcp/Tools/
git commit -m "feat: implement MCP write tools for all modules"
```

---

### Task 5.4: Implement MCP Resources

**Files:**
- Create: `app/Mcp/Resources/UserProfileResource.php`
- Create: `app/Mcp/Resources/WorkoutHistoryResource.php`

- [ ] **Step 1: Create UserProfileResource**

```php
<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

#[Name('user-profile')]
#[Description('Access the authenticated user\'s profile information.')]
#[MimeType('application/json')]
class UserProfileResource extends Resource
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required');
        }

        return Response::structured([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'weight' => $user->weight,
            'height' => $user->height,
            'created_at' => $user->created_at->toISOString(),
        ]);
    }
}
```

- [ ] **Step 2: Create WorkoutHistoryResource**

```php
<?php

namespace App\Mcp\Resources;

use App\Models\Workout;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Resource;

#[Name('workout-history')]
#[Description('Access the user\'s workout history summary.')]
#[MimeType('application/json')]
class WorkoutHistoryResource extends Resource
{
    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Authentication required');
        }

        $workouts = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get();

        $totalVolume = $workouts->sum(function ($workout) {
            return $workout->exercises->sum(function ($exercise) {
                return $exercise->sets->sum(function ($set) {
                    return ($set->weight ?? 0) * ($set->reps ?? 0);
                });
            });
        });

        return Response::structured([
            'total_workouts' => $workouts->count(),
            'total_volume' => $totalVolume,
            'workouts' => $workouts->toArray(),
        ]);
    }
}
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add app/Mcp/Resources/
git commit -m "feat: implement MCP resources for user profile and workout history"
```

---

### Task 5.5: MCP Auth + Rate Limiting

**Files:**
- Modify: `routes/ai.php`
- Create: `app/Providers/McpServiceProvider.php`

- [ ] **Step 1: Configure MCP auth middleware**

```php
// routes/ai.php
use App\Mcp\Servers\MegalomaniacServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/megalomaniac', MegalomaniacServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
```

- [ ] **Step 2: Create McpServiceProvider**

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('mcp', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
```

- [ ] **Step 3: Register provider**

```php
// config/app.php
'providers' => [
    // ...
    App\Providers\McpServiceProvider::class,
],
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add routes/ai.php app/Providers/McpServiceProvider.php config/app.php
git commit -m "feat: configure MCP authentication and rate limiting"
```

---

## Final Verification

### Task F.1: Full Test Suite

- [ ] **Step 1: Run all backend tests**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 2: Run lint**

Run: `vendor/bin/pint --dirty`
Expected: No errors

- [ ] **Step 3: Run TypeScript check**

Run: `npm run types`
Expected: No errors

- [ ] **Step 4: Run build**

Run: `npm run build`
Expected: Build succeeds

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: final verification and cleanup"
```

---

## Summary

| Phase | Tasks | Files Created | Files Modified |
|-------|-------|---------------|----------------|
| 1. Updates | 3 | 0 | 2 |
| 2. API | 6 | ~60 | ~10 |
| 3. AI | 4 | ~25 | ~5 |
| 4. Agents | 1 | ~8 | ~2 |
| 5. MCP | 5 | ~20 | ~3 |
| Final | 1 | 0 | 0 |
| **Total** | **20** | **~113** | **~22** |
