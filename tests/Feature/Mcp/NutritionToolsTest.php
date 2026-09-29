<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\NutritionReadTool;
use App\Mcp\Tools\NutritionWriteTool;
use App\Models\Food;
use App\Models\MealLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a meal log idempotently and adds items with multiplied macros', function () {
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

    foreach ([1, 2] as $attempt) {
        MegalomaniacServer::actingAs($user)
            ->tool(NutritionWriteTool::class, [
                'action' => 'create_meal_log',
                'date' => now()->toDateString(),
                'meal_type' => 'breakfast',
            ])
            ->assertOk();
    }

    expect(MealLog::where('user_id', $user->id)->count())->toBe(1);

    $mealLog = MealLog::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(NutritionWriteTool::class, [
            'action' => 'add_food_item',
            'meal_log_id' => $mealLog->id,
            'food_id' => $food->id,
            'quantity' => 2,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('meal_item.calories_snapshot', fn ($value) => (float) $value === 200.0)
            ->where('meal_item.protein_snapshot', '10.00')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(NutritionReadTool::class, ['days' => 7])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('count', 1)
            ->etc());
});

it('creates foods and deletes meal items through mcp', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(NutritionWriteTool::class, [
            'action' => 'create_food',
            'name' => 'Pollo',
            'calories' => 165,
            'protein' => 31,
        ])
        ->assertOk();

    $food = Food::where('name', 'Pollo')->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(NutritionWriteTool::class, [
            'action' => 'create_meal_log',
            'meal_type' => 'lunch',
        ])
        ->assertOk();

    $mealLog = MealLog::where('user_id', $user->id)->firstOrFail();

    $response = MegalomaniacServer::actingAs($user)
        ->tool(NutritionWriteTool::class, [
            'action' => 'add_food_item',
            'meal_log_id' => $mealLog->id,
            'food_id' => $food->id,
            'quantity' => 1,
        ])
        ->assertOk();

    expect($mealLog->items()->count())->toBe(1);

    MegalomaniacServer::actingAs($user)
        ->tool(NutritionWriteTool::class, [
            'action' => 'delete_meal_item',
            'meal_item_id' => $mealLog->items()->first()->id,
        ])
        ->assertOk();

    expect($mealLog->items()->count())->toBe(0);
});
