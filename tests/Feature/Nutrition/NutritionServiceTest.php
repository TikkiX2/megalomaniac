<?php

use App\Models\Food;
use App\Models\MealItem;
use App\Models\MealLog;
use App\Models\User;
use App\Services\Nutrition\NutritionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('upserts meal logs per date and type and computes macros with the quantity multiplier', function () {
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

    $service = app(NutritionService::class);

    $log = $service->createMealLog($user, now()->toDateString(), 'breakfast');
    $sameLog = $service->createMealLog($user, now()->toDateString(), 'breakfast');

    expect($sameLog->id)->toBe($log->id)
        ->and(MealLog::where('user_id', $user->id)->count())->toBe(1);

    $item = $service->addMealItem($user, $log, ['food_id' => $food->id, 'quantity' => 2]);

    expect((float) $item->calories_snapshot)->toBe(200.0)
        ->and((float) $item->protein_snapshot)->toBe(10.0)
        ->and((float) $item->carbs_snapshot)->toBe(40.0)
        ->and((float) $item->fats_snapshot)->toBe(4.0);

    $service->deleteMealItem($user, $item);
    expect(MealItem::find($item->id))->toBeNull();
});

it('rejects meal items without a valid food or quantity', function () {
    $user = User::factory()->create();
    $service = app(NutritionService::class);
    $log = $service->createMealLog($user, now()->toDateString(), 'lunch');

    expect(fn () => $service->addMealItem($user, $log, ['quantity' => 1]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->addMealItem($user, $log, ['food_id' => 999, 'quantity' => 1]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->addMealItem($user, $log, ['food_id' => 1, 'quantity' => 0]))
        ->toThrow(InvalidArgumentException::class);
});

it('creates and searches foods', function () {
    $user = User::factory()->create();
    $service = app(NutritionService::class);

    $food = $service->createFood($user, [
        'name' => 'Pollo',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fats' => 3.6,
    ]);

    expect($food->name)->toBe('Pollo')
        ->and($service->searchFoods($user, 'poll'))->toHaveCount(1);
});
