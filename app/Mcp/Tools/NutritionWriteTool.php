<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\MealItem;
use App\Models\MealLog;
use App\Services\Nutrition\NutritionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class NutritionWriteTool extends Tool
{
    protected string $name = 'nutrition-write';

    protected string $description = 'Create meal logs, add or remove food items (macros are snapshotted from the food times the quantity) and create foods for the authenticated user.';

    public function __construct(protected NutritionService $nutrition) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform: create_meal_log, add_food_item, delete_meal_item, create_food')->enum(['create_meal_log', 'add_food_item', 'delete_meal_item', 'create_food'])->required(),
            'date' => $schema->string()->description('Date in YYYY-MM-DD format (create_meal_log, default: today)'),
            'meal_type' => $schema->string()->description('Meal type: breakfast, lunch, dinner, snack (create_meal_log)')->enum(['breakfast', 'lunch', 'dinner', 'snack']),
            'meal_log_id' => $schema->integer()->description('Meal log ID (required for add_food_item)'),
            'meal_item_id' => $schema->integer()->description('Meal item ID (delete_meal_item)'),
            'food_id' => $schema->integer()->description('Food ID (required for add_food_item)'),
            'quantity' => $schema->number()->description('Serving multiplier; macros are multiplied by it (add_food_item)')->min(0.01),
            'name' => $schema->string()->description('Food name (create_food)'),
            'brand' => $schema->string()->description('Brand (create_food)'),
            'calories' => $schema->number()->description('Calories per serving (create_food)'),
            'protein' => $schema->number()->description('Protein grams per serving (create_food)'),
            'carbs' => $schema->number()->description('Carb grams per serving (create_food)'),
            'fats' => $schema->number()->description('Fat grams per serving (create_food)'),
            'serving_size' => $schema->number()->description('Serving size (create_food)'),
            'serving_unit' => $schema->string()->description('Serving unit (create_food)'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action');

        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_meal_log' => $this->createMealLog($request, $user),
                'add_food_item' => $this->addFoodItem($request, $user),
                'delete_meal_item' => $this->deleteMealItem($request, $user),
                'create_food' => $this->createFood($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createMealLog(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'meal_type' => ['required', 'string', 'in:breakfast,lunch,dinner,snack'],
        ]);

        $mealLog = $this->nutrition->createMealLog(
            $user,
            (string) $request->get('date', now()->toDateString()),
            (string) $request->get('meal_type'),
        );

        return Response::structured([
            'meal_log' => $mealLog->fresh(),
            'message' => 'Meal log ready.',
        ]);
    }

    private function addFoodItem(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'meal_log_id' => ['required', 'integer'],
            'food_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        $mealLog = MealLog::where('id', $request->get('meal_log_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $mealLog) {
            return Response::error('Meal log not found or unauthorized.');
        }

        $mealItem = $this->nutrition->addMealItem($user, $mealLog, [
            'food_id' => $request->get('food_id'),
            'quantity' => $request->get('quantity'),
        ]);

        return Response::structured([
            'meal_item' => $mealItem->fresh()->load('food'),
            'message' => 'Food item added to meal log.',
        ]);
    }

    private function deleteMealItem(Request $request, $user): Response|ResponseFactory
    {
        $mealItem = MealItem::with('mealLog')->find($request->get('meal_item_id', 0));

        if (! $mealItem) {
            return Response::error('Meal item not found.');
        }

        $this->nutrition->deleteMealItem($user, $mealItem);

        return Response::structured([
            'message' => 'Meal item deleted.',
        ]);
    }

    private function createFood(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'calories' => ['nullable', 'numeric', 'min:0'],
        ]);

        $food = $this->nutrition->createFood($user, [
            'name' => $request->get('name'),
            'brand' => $request->get('brand'),
            'calories' => $request->get('calories', 0),
            'protein' => $request->get('protein', 0),
            'carbs' => $request->get('carbs', 0),
            'fats' => $request->get('fats', 0),
            'serving_size' => $request->get('serving_size'),
            'serving_unit' => $request->get('serving_unit'),
        ]);

        return Response::structured([
            'food' => $food->fresh(),
            'message' => 'Food created successfully.',
        ]);
    }
}
