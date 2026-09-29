<?php

namespace App\Ai\Tools;

use App\Models\MealItem;
use App\Models\User;
use App\Services\Nutrition\NutritionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class NutritionActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected NutritionService $nutrition,
    ) {}

    public function description(): Stringable|string
    {
        return 'Record the user\'s nutrition: create a meal log, add or remove meal items with macros snapped from the food, and create foods. Use this when the user asks to log what they ate or drank.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tu nutrición').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'log_meal' => 'registrar una comida',
            'add_meal_item' => 'añadir un alimento a la comida',
            'delete_meal_item' => 'eliminar un alimento de la comida',
            'create_food' => 'crear un alimento',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'log_meal' => $this->logMeal($request),
                'add_meal_item' => $this->addMealItem($request),
                'delete_meal_item' => $this->deleteMealItem($request),
                'create_food' => $this->createFood($request),
                default => $this->error('Invalid action. Use: log_meal, add_meal_item, delete_meal_item, create_food'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function logMeal(Request $request): string
    {
        $log = $this->nutrition->createMealLog(
            $this->user,
            (string) ($request['date'] ?? now()->toDateString()),
            (string) ($request['meal_type'] ?? 'snack'),
        );

        return $this->success('Meal log ready', ['meal_log' => $log->toArray()]);
    }

    private function addMealItem(Request $request): string
    {
        $date = (string) ($request['date'] ?? now()->toDateString());
        $mealType = (string) ($request['meal_type'] ?? 'snack');
        $log = $this->nutrition->createMealLog($this->user, $date, $mealType);

        $foodId = $request['food_id'] ?? null;

        if (! $foodId && filled($request['food_name'] ?? null)) {
            $food = $this->nutrition->createFood($this->user, [
                'name' => $request['food_name'],
                'calories' => $request['calories'] ?? 0,
                'protein' => $request['protein'] ?? 0,
                'carbs' => $request['carbs'] ?? 0,
                'fats' => $request['fats'] ?? 0,
            ]);
            $foodId = $food->id;
        }

        $item = $this->nutrition->addMealItem($this->user, $log, [
            'food_id' => $foodId,
            'quantity' => $request['quantity'] ?? 1,
        ]);

        return $this->success('Meal item added', [
            'meal_item' => $item->load('food')->toArray(),
        ]);
    }

    private function deleteMealItem(Request $request): string
    {
        $item = MealItem::with('mealLog')->find($request['meal_item_id'] ?? 0);

        if (! $item) {
            return $this->error('Meal item not found');
        }

        $this->nutrition->deleteMealItem($this->user, $item);

        return $this->success('Meal item deleted', ['meal_item' => ['id' => $item->id]]);
    }

    private function createFood(Request $request): string
    {
        $food = $this->nutrition->createFood($this->user, [
            'name' => $request['food_name'] ?? $request['name'] ?? null,
            'brand' => $request['brand'] ?? null,
            'calories' => $request['calories'] ?? 0,
            'protein' => $request['protein'] ?? 0,
            'carbs' => $request['carbs'] ?? 0,
            'fats' => $request['fats'] ?? 0,
            'serving_size' => $request['serving_size'] ?? null,
            'serving_unit' => $request['serving_unit'] ?? null,
        ]);

        return $this->success('Food created', ['food' => $food->toArray()]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['log_meal', 'add_meal_item', 'delete_meal_item', 'create_food'])
                ->description('Action to perform')
                ->required(),
            'date' => $schema->string()->description('Date YYYY-MM-DD (defaults to today)'),
            'meal_type' => $schema->string()->description('Meal type: breakfast, lunch, dinner, snack')->enum(['breakfast', 'lunch', 'dinner', 'snack']),
            'meal_item_id' => $schema->integer()->description('Meal item ID (delete_meal_item)'),
            'food_id' => $schema->integer()->description('Existing food ID (add_meal_item)'),
            'food_name' => $schema->string()->description('Food name; a new food is created when no food_id is given (add_meal_item, create_food)'),
            'brand' => $schema->string()->description('Brand (create_food)'),
            'quantity' => $schema->number()->description('Serving multiplier; macros are multiplied by it (add_meal_item, default 1)'),
            'calories' => $schema->number()->description('Calories per serving (create_food, or inline food in add_meal_item)'),
            'protein' => $schema->number()->description('Protein grams per serving (create_food, add_meal_item)'),
            'carbs' => $schema->number()->description('Carb grams per serving (create_food, add_meal_item)'),
            'fats' => $schema->number()->description('Fat grams per serving (create_food, add_meal_item)'),
            'serving_size' => $schema->number()->description('Serving size (create_food)'),
            'serving_unit' => $schema->string()->description('Serving unit (create_food)'),
        ];
    }
}
