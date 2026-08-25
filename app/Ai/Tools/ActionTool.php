<?php

namespace App\Ai\Tools;

use App\Models\MealLog;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActionTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Perform actions on behalf of the user: create workouts, log meals, add purchases. Use this when the user asks to record or create something.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = $request['action'] ?? '';

        return match ($action) {
            'create_workout' => $this->createWorkout($request),
            'log_set' => $this->logSet($request),
            'log_meal' => $this->logMeal($request),
            'add_purchase' => $this->addPurchase($request),
            default => json_encode(['error' => 'Invalid action. Use: create_workout, log_set, log_meal, add_purchase']),
        };
    }

    private function createWorkout(Request $request): string
    {
        $workout = Workout::create([
            'user_id' => $this->user->id,
            'started_at' => $request['started_at'] ?? now()->toIso8601String(),
            'notes' => $request['notes'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Workout created',
            'workout' => $workout->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function logSet(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($q) => $q->where('user_id', $this->user->id))
            ->findOrFail($request['workout_exercise_id']);

        $set = WorkoutSet::create([
            'workout_exercise_id' => $workoutExercise->id,
            'weight' => $request['weight'] ?? 0,
            'reps' => $request['reps'] ?? 0,
            'rpe' => $request['rpe'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Set logged',
            'set' => $set->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function logMeal(Request $request): string
    {
        $log = MealLog::create([
            'user_id' => $this->user->id,
            'date' => $request['date'] ?? now()->toDateString(),
            'meal_type' => $request['meal_type'] ?? 'snack',
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Meal log created',
            'meal_log' => $log->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    private function addPurchase(Request $request): string
    {
        $purchase = Purchase::create([
            'user_id' => $this->user->id,
            'description' => $request['description'] ?? $request['name'] ?? 'Purchase',
            'amount' => $request['amount'] ?? 0,
            'purchase_date' => $request['purchase_date'] ?? $request['date'] ?? now()->toDateString(),
            'category_id' => $request['category_id'] ?? null,
            'currency_id' => $request['currency_id'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]);

        return json_encode([
            'success' => true,
            'message' => 'Purchase added',
            'purchase' => $purchase->toArray(),
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_workout', 'log_set', 'log_meal', 'add_purchase'])
                ->description('Action to perform')
                ->required(),
            'started_at' => $schema->string()->description('ISO 8601 datetime (for create_workout)'),
            'notes' => $schema->string()->description('Notes (for create_workout)'),
            'workout_exercise_id' => $schema->integer()->description('Workout Exercise ID (for log_set)'),
            'weight' => $schema->number()->description('Weight in kg/lbs (for log_set)'),
            'reps' => $schema->integer()->description('Number of reps (for log_set)'),
            'rpe' => $schema->number()->description('Rate of perceived exertion 1-10 (for log_set)'),
            'date' => $schema->string()->description('Date YYYY-MM-DD (for log_meal, add_purchase)'),
            'food_name' => $schema->string()->description('Food name (for log_meal)'),
            'calories' => $schema->number()->description('Calories (for log_meal)'),
            'protein' => $schema->number()->description('Protein in grams (for log_meal)'),
            'carbs' => $schema->number()->description('Carbs in grams (for log_meal)'),
            'fats' => $schema->number()->description('Fats in grams (for log_meal)'),
            'quantity' => $schema->number()->description('Quantity (for log_meal)'),
            'meal_type' => $schema->string()->description('Meal type: breakfast, lunch, dinner, snack (for log_meal)'),
            'name' => $schema->string()->description('Purchase name (for add_purchase)'),
            'amount' => $schema->number()->description('Amount (for add_purchase)'),
            'category_id' => $schema->integer()->description('Category ID (for add_purchase)'),
            'currency_id' => $schema->integer()->description('Currency ID (for add_purchase)'),
        ];
    }
}
