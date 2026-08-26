<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Food;
use App\Models\MealItem;
use App\Models\MealLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class NutritionWriteTool extends Tool
{
    protected string $name = 'nutrition-write';

    protected string $description = 'Create meal logs and add food items for the authenticated user.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_meal_log, add_food_item')->enum(['create_meal_log', 'add_food_item'])->required(),
            'date' => $schema->string()->description('Date in YYYY-MM-DD format (for create_meal_log, default: today)'),
            'meal_type' => $schema->string()->description('Meal type: breakfast, lunch, dinner, snack (for create_meal_log)')->enum(['breakfast', 'lunch', 'dinner', 'snack']),
            'meal_log_id' => $schema->integer()->description('Meal log ID (required for add_food_item)'),
            'food_id' => $schema->integer()->description('Food ID (required for add_food_item)'),
            'quantity' => $schema->number()->description('Quantity in grams (required for add_food_item)')->min(1),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        return match ($action) {
            'create_meal_log' => $this->createMealLog($request, $user),
            'add_food_item' => $this->addFoodItem($request, $user),
            default => Response::error("Invalid action: {$action}"),
        };
    }

    private function createMealLog(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'meal_type' => ['required', 'string', 'in:breakfast,lunch,dinner,snack'],
        ]);

        $mealLog = MealLog::create([
            'user_id' => $user->id,
            'date' => $request->get('date', now()->toDateString()),
            'meal_type' => $request->get('meal_type'),
        ]);

        return Response::structured([
            'meal_log' => $mealLog->fresh(),
            'message' => 'Meal log created successfully.',
        ]);
    }

    private function addFoodItem(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'meal_log_id' => ['required', 'integer', 'exists:meal_logs,id'],
            'food_id' => ['required', 'integer', 'exists:foods,id'],
            'quantity' => ['required', 'numeric', 'min:1'],
        ]);

        $mealLog = MealLog::where('id', $request->get('meal_log_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $mealLog) {
            return Response::error('Meal log not found or unauthorized.');
        }

        $food = Food::findOrFail($request->get('food_id'));
        $quantity = (float) $request->get('quantity');
        $ratio = $quantity / 100;

        $mealItem = MealItem::create([
            'meal_log_id' => $mealLog->id,
            'food_id' => $food->id,
            'quantity' => $quantity,
            'calories_snapshot' => (int) round($food->calories * $ratio),
            'protein_snapshot' => round($food->protein * $ratio, 2),
            'carbs_snapshot' => round($food->carbs * $ratio, 2),
            'fats_snapshot' => round($food->fats * $ratio, 2),
        ]);

        return Response::structured([
            'meal_item' => $mealItem->fresh()->load('food'),
            'message' => 'Food item added to meal log.',
        ]);
    }
}
