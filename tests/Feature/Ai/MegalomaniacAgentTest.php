<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\FinanceActionTool;
use App\Ai\Tools\FinanceQueryTool;
use App\Ai\Tools\ForgetMemoryTool;
use App\Ai\Tools\FreelanceActionTool;
use App\Ai\Tools\FreelanceQueryTool;
use App\Ai\Tools\GroceryActionTool;
use App\Ai\Tools\GroceryQueryTool;
use App\Ai\Tools\GymActionTool;
use App\Ai\Tools\GymQueryTool;
use App\Ai\Tools\HealthActionTool;
use App\Ai\Tools\HealthQueryTool;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Ai\Tools\LoadSkillTool;
use App\Ai\Tools\ManageAgentsTool;
use App\Ai\Tools\NutritionActionTool;
use App\Ai\Tools\NutritionQueryTool;
use App\Ai\Tools\PeopleActionTool;
use App\Ai\Tools\PeopleQueryTool;
use App\Ai\Tools\ProjectActionTool;
use App\Ai\Tools\PromoteMemoryTool;
use App\Ai\Tools\RememberMemoryTool;
use App\Ai\Tools\SupplementActionTool;
use App\Ai\Tools\SupplementQueryTool;
use App\Ai\Tools\TaskActionTool;
use App\Ai\Tools\TaskQueryTool;
use App\Ai\Tools\WebFetchTool;
use App\Ai\Tools\WebSearchTool;
use App\Models\Currency;
use App\Models\Exercise;
use App\Models\GroceryItem;
use App\Models\MealLog;
use App\Models\Purchase;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use App\Services\Nutrition\NutritionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('megalomaniac agent has correct instructions', function () {
    $user = User::factory()->create();
    $agent = new MegalomaniacAgent($user);

    expect($agent->instructions())->toContain('Megalomaniac');
    expect($agent->instructions())->toContain('fitness');
});

test('does not advertise write tools when the turn is read only', function () {
    $agent = new MegalomaniacAgent(User::factory()->create(), ['tasks']);

    expect($agent->instructions())
        ->not->toContain('TaskActionTool')
        ->not->toContain('GymActionTool')
        ->toContain('Only read tools are available');
});

test('advertises the matching write tool when write groups are enabled', function (array $groups, string $tool) {
    $agent = new MegalomaniacAgent(User::factory()->create(), $groups);

    expect($agent->instructions())->toContain($tool);
})->with([
    [['actions'], 'TaskActionTool'],
    [['workout'], 'GymActionTool'],
    [['*'], 'TaskActionTool'],
]);

test('megalomaniac agent has correct tools', function () {
    $user = User::factory()->create();
    $agent = new MegalomaniacAgent($user);

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(29);
    expect($tools[0])->toBeInstanceOf(TaskQueryTool::class);
    expect($tools[1])->toBeInstanceOf(ProjectActionTool::class);
    expect($tools[2])->toBeInstanceOf(TaskActionTool::class);
    expect($tools[3])->toBeInstanceOf(GymQueryTool::class);
    expect($tools[4])->toBeInstanceOf(GymActionTool::class);
    expect($tools[5])->toBeInstanceOf(FinanceQueryTool::class);
    expect($tools[6])->toBeInstanceOf(FinanceActionTool::class);
    expect($tools[7])->toBeInstanceOf(NutritionQueryTool::class);
    expect($tools[8])->toBeInstanceOf(NutritionActionTool::class);
    expect($tools[9])->toBeInstanceOf(GroceryQueryTool::class);
    expect($tools[10])->toBeInstanceOf(GroceryActionTool::class);
    expect($tools[11])->toBeInstanceOf(SupplementQueryTool::class);
    expect($tools[12])->toBeInstanceOf(SupplementActionTool::class);
    expect($tools[13])->toBeInstanceOf(FreelanceQueryTool::class);
    expect($tools[14])->toBeInstanceOf(FreelanceActionTool::class);
    expect($tools[15])->toBeInstanceOf(PeopleQueryTool::class);
    expect($tools[16])->toBeInstanceOf(PeopleActionTool::class);
    expect($tools[17])->toBeInstanceOf(HealthQueryTool::class);
    expect($tools[18])->toBeInstanceOf(HealthActionTool::class);
    expect($tools[19])->toBeInstanceOf(IntegrationCatalogTool::class);
    expect($tools[20])->toBeInstanceOf(IntegrationCallTool::class);
    expect($tools[21])->toBeInstanceOf(ManageAgentsTool::class);
    expect($tools[22])->toBeInstanceOf(LoadSkillTool::class);
    expect($tools[23])->toBeInstanceOf(RememberMemoryTool::class);
    expect($tools[24])->toBeInstanceOf(ForgetMemoryTool::class);
    expect($tools[25])->toBeInstanceOf(PromoteMemoryTool::class);
    expect($tools[26])->toBeInstanceOf(WebSearchTool::class);
    expect($tools[27])->toBeInstanceOf(WebFetchTool::class);
    expect($tools[28])->toBeInstanceOf(AskUserTool::class);
});

test('workout query tool returns workouts', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $tool = new GymQueryTool($user);
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

    $tool = new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
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

test('nutrition action tool logs meal', function () {
    $user = User::factory()->create();

    $tool = new NutritionActionTool($user, app(NutritionService::class));
    $result = $tool->handle(new Request([
        'action' => 'log_meal',
        'date' => now()->toDateString(),
        'meal_type' => 'lunch',
    ]));

    $data = json_decode($result, true);

    expect($data['success'])->toBeTrue()
        ->and($data['meal_log']['meal_type'])->toBe('lunch');
});

test('action tool logs sets with automatic numbering', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $tool = new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
    $result = $tool->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExercise->id,
        'weight' => 60,
        'reps' => 8,
        'completed' => true,
    ]));

    $data = json_decode($result, true);

    expect($data['success'])->toBeTrue()
        ->and($data['set']['set_number'])->toBe(1);
});

test('action tool copies the routine template when creating a workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach(Exercise::factory()->create()->id, ['order' => 1, 'target_sets' => 2]);

    $tool = new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
    $data = json_decode($tool->handle(new Request([
        'action' => 'create_workout',
        'routine_id' => $routine->id,
    ])), true);

    expect($data['success'])->toBeTrue()
        ->and($data['workout']['exercises'])->toHaveCount(1);
});

test('action tool returns error for unknown action', function () {
    $user = User::factory()->create();

    $tool = new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
    $request = new Request([
        'action' => 'unknown_action',
    ]);

    $result = $tool->handle($request);
    $data = json_decode($result, true);

    expect($data)->toHaveKey('error');
    expect($data['error'])->toContain('Invalid action');
});
