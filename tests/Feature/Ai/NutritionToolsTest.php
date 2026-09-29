<?php

use App\Ai\Tools\NutritionActionTool;
use App\Ai\Tools\SupplementActionTool;
use App\Ai\Tools\SupplementQueryTool;
use App\Models\Food;
use App\Models\MealLog;
use App\Models\User;
use App\Services\Nutrition\NutritionService;
use App\Services\Supplement\SupplementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function nutritionTool(User $user): NutritionActionTool
{
    return new NutritionActionTool($user, app(NutritionService::class));
}

it('logs a meal with inline food and macros multiplied by quantity', function () {
    $user = User::factory()->create();

    $result = json_decode((string) nutritionTool($user)->handle(new Request([
        'action' => 'add_meal_item',
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
        'food_name' => 'Arroz',
        'calories' => 130,
        'protein' => 2.7,
        'carbs' => 28,
        'fats' => 0.3,
        'quantity' => 2,
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and((float) $result['meal_item']['calories_snapshot'])->toBe(260.0)
        ->and(MealLog::where('user_id', $user->id)->count())->toBe(1);
});

it('adds items from an existing food and deletes them', function () {
    $user = User::factory()->create();
    $food = Food::create([
        'name' => 'Avena',
        'calories' => 100,
        'protein' => 5,
        'carbs' => 20,
        'fats' => 2,
        'serving_size' => 50,
        'serving_unit' => 'g',
    ]);

    $added = json_decode((string) nutritionTool($user)->handle(new Request([
        'action' => 'add_meal_item',
        'food_id' => $food->id,
        'quantity' => 1,
    ])), true);

    expect($added['success'])->toBeTrue();

    $deleted = json_decode((string) nutritionTool($user)->handle(new Request([
        'action' => 'delete_meal_item',
        'meal_item_id' => $added['meal_item']['id'],
    ])), true);

    expect($deleted['success'])->toBeTrue();
});

it('manages supplements and logs intake', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();

    $action = new SupplementActionTool($user, app(SupplementService::class));

    $created = json_decode((string) $action->handle(new Request([
        'action' => 'create_supplement',
        'name' => 'Creatina',
        'stock_quantity' => 10,
        'low_stock_threshold' => 3,
    ])), true);

    expect($created['success'])->toBeTrue();

    $supplementId = $created['supplement']['id'];

    $logged = json_decode((string) $action->handle(new Request([
        'action' => 'log_supplement',
        'supplement_id' => $supplementId,
    ])), true);

    expect($logged['success'])->toBeTrue()
        ->and($logged['supplement']['stock_quantity'])->toBe(9);

    $query = json_decode((string) (new SupplementQueryTool($user, app(SupplementService::class)))
        ->handle(new Request([])), true);

    expect($query['supplements'])->toHaveCount(1)
        ->and($query['recent_logs'])->toHaveCount(1);

    $foreign = json_decode((string) (new SupplementActionTool($intruder, app(SupplementService::class)))
        ->handle(new Request(['action' => 'log_supplement', 'supplement_id' => $supplementId])), true);

    expect($foreign['success'] ?? false)->toBeFalse();
});
