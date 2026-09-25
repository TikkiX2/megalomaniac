<?php

use App\Models\Exercise;
use App\Models\Food;
use App\Models\GroceryItem;
use App\Models\Supplement;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('gym module: can create and retrieve exercises', function () {
    $response = $this->postJson('/gym/exercises', [
        'name' => 'Bench Press',
        'muscle_group' => 'Chest',
    ]);

    $response->assertStatus(201)
        ->assertJson(['name' => 'Bench Press']);

    $this->getJson('/gym/exercises')
        ->assertStatus(200)
        ->assertJsonCount(1);
});

test('gym module: can create routine with exercises', function () {
    $exercise = Exercise::create(['name' => 'Squat', 'muscle_group' => 'Legs']);

    $response = $this->postJson('/gym/routines', [
        'name' => 'Leg Day',
        'exercises' => [
            ['id' => $exercise->id, 'order' => 1],
        ],
    ]);

    $response->assertStatus(201)
        ->assertJson(['name' => 'Leg Day']);

    $this->assertDatabaseHas('routine_exercises', [
        'exercise_id' => $exercise->id,
    ]);
});

test('nutrition module: can search and log food', function () {
    $food = Food::create([
        'name' => 'Chicken Breast',
        'calories' => 165,
        'protein' => 31,
        'carbs' => 0,
        'fats' => 3.6,
    ]);

    $this->getJson('/nutrition/foods/search?query=Chicken')
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'Chicken Breast']);

    $this->postJson('/nutrition/logs/items', [
        'date' => now()->toDateString(),
        'meal_type' => 'dinner',
        'food_id' => $food->id,
        'quantity' => 2, // 200g equivalent
    ])->assertStatus(201);

    $this->assertDatabaseHas('meal_items', [
        'calories_snapshot' => 330, // 165 * 2
    ]);
});

test('supplement module: can manage supplements and log intake', function () {
    $supplement = Supplement::create([
        'user_id' => $this->user->id,
        'name' => 'Whey Protein',
        'stock_quantity' => 30,
        'low_stock_threshold' => 5,
    ]);

    $this->postJson("/supplements/{$supplement->id}/log")
        ->assertRedirect();

    $this->assertDatabaseHas('supplements', [
        'id' => $supplement->id,
        'stock_quantity' => 29,
    ]);

    $this->assertDatabaseHas('supplement_logs', [
        'supplement_id' => $supplement->id,
        'user_id' => $this->user->id,
    ]);
});

test('grocery module: can manage grocery items', function () {
    $this->postJson('/grocery/items', [
        'name' => 'Milk',
        'current_stock' => 1,
        'target_stock' => 3,
        'unit' => 'L',
    ])->assertRedirect();

    $item = GroceryItem::first();

    $this->postJson("/grocery/{$item->id}/consume")
        ->assertRedirect();

    $this->assertEquals(0, $item->fresh()->current_stock);
});
