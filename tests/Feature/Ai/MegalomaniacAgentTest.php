<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\ActionTool;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\FinanceQueryTool;
use App\Ai\Tools\GroceryQueryTool;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Ai\Tools\ManageAgentsTool;
use App\Ai\Tools\NutritionQueryTool;
use App\Ai\Tools\TaskQueryTool;
use App\Ai\Tools\WebFetchTool;
use App\Ai\Tools\WebSearchTool;
use App\Ai\Tools\WorkoutQueryTool;
use App\Models\Currency;
use App\Models\GroceryItem;
use App\Models\MealLog;
use App\Models\Purchase;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('megalomaniac agent has correct instructions', function () {
    $user = User::factory()->create();
    $agent = new MegalomaniacAgent($user);

    expect($agent->instructions())->toContain('Megalomaniac');
    expect($agent->instructions())->toContain('fitness');
});

test('megalomaniac agent has correct tools', function () {
    $user = User::factory()->create();
    $agent = new MegalomaniacAgent($user);

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(12);
    expect($tools[0])->toBeInstanceOf(TaskQueryTool::class);
    expect($tools[1])->toBeInstanceOf(WorkoutQueryTool::class);
    expect($tools[2])->toBeInstanceOf(FinanceQueryTool::class);
    expect($tools[3])->toBeInstanceOf(NutritionQueryTool::class);
    expect($tools[4])->toBeInstanceOf(GroceryQueryTool::class);
    expect($tools[5])->toBeInstanceOf(ActionTool::class);
    expect($tools[6])->toBeInstanceOf(IntegrationCatalogTool::class);
    expect($tools[7])->toBeInstanceOf(IntegrationCallTool::class);
    expect($tools[8])->toBeInstanceOf(ManageAgentsTool::class);
    expect($tools[9])->toBeInstanceOf(WebSearchTool::class);
    expect($tools[10])->toBeInstanceOf(WebFetchTool::class);
    expect($tools[11])->toBeInstanceOf(AskUserTool::class);
});

test('workout query tool returns workouts', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $tool = new WorkoutQueryTool($user);
    $request = new Request(['days' => 30]);
    $result = $tool->handle($request);

    $data = json_decode($result, true);
    expect($data)->toHaveCount(1);
    expect($data[0]['id'])->toBe($workout->id);
});

test('finance query tool returns purchases', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
    Purchase::create([
        'user_id' => $user->id,
        'currency_id' => $currency->id,
        'amount' => 29.99,
        'description' => 'Test purchase',
        'purchase_date' => now(),
    ]);

    $tool = new FinanceQueryTool($user);
    $request = new Request(['days' => 30, 'type' => 'purchases']);
    $result = $tool->handle($request);

    $data = json_decode($result, true);
    expect($data)->toHaveCount(1);
    expect($data[0]['amount'])->toBe('29.99');
});

test('nutrition query tool returns meal logs', function () {
    $user = User::factory()->create();
    $mealLog = MealLog::create([
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
    ]);

    $tool = new NutritionQueryTool($user);
    $request = new Request(['days' => 7]);
    $result = $tool->handle($request);

    $data = json_decode($result, true);
    expect($data)->toHaveKey('logs');
    expect($data['logs'])->toHaveCount(1);
});

test('grocery query tool returns items', function () {
    $user = User::factory()->create();
    GroceryItem::factory()->create(['user_id' => $user->id]);

    $tool = new GroceryQueryTool($user);
    $request = new Request([]);
    $result = $tool->handle($request);

    $data = json_decode($result, true);
    expect($data)->toHaveKey('items');
    expect($data['total_items'])->toBe(1);
});

test('action tool creates workout', function () {
    $user = User::factory()->create();

    $tool = new ActionTool($user);
    $request = new Request([
        'action' => 'create_workout',
        'started_at' => now()->toIso8601String(),
        'notes' => 'Test workout',
    ]);

    $result = $tool->handle($request);
    $data = json_decode($result, true);

    expect($data)->toHaveKey('success');
    expect($data['success'])->toBeTrue();
    expect($data)->toHaveKey('workout');
    expect($data['workout']['user_id'])->toBe($user->id);
});

test('action tool logs meal', function () {
    $user = User::factory()->create();

    $tool = new ActionTool($user);
    $request = new Request([
        'action' => 'log_meal',
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
    ]);

    $result = $tool->handle($request);
    $data = json_decode($result, true);

    expect($data)->toHaveKey('success');
    expect($data['success'])->toBeTrue();
    expect($data)->toHaveKey('meal_log');
    expect($data['meal_log']['meal_type'])->toBe('lunch');
});

test('action tool adds purchase', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $tool = new ActionTool($user);
    $request = new Request([
        'action' => 'add_purchase',
        'description' => 'Groceries',
        'amount' => 29.99,
        'purchase_date' => now()->toDateString(),
        'currency_id' => $currency->id,
    ]);

    $result = $tool->handle($request);
    $data = json_decode($result, true);

    expect($data)->toHaveKey('success');
    expect($data['success'])->toBeTrue();
    expect($data)->toHaveKey('purchase');
    expect($data['purchase']['amount'])->toBe('29.99');
    expect($data['purchase']['description'])->toBe('Groceries');
});

test('action tool returns error for unknown action', function () {
    $user = User::factory()->create();

    $tool = new ActionTool($user);
    $request = new Request([
        'action' => 'unknown_action',
    ]);

    $result = $tool->handle($request);
    $data = json_decode($result, true);

    expect($data)->toHaveKey('error');
    expect($data['error'])->toContain('Invalid action');
});
