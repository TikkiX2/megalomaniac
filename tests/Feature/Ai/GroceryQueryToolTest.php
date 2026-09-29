<?php

use App\Ai\Tools\GroceryQueryTool;
use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('reports low stock using current and target stock', function () {
    $user = User::factory()->create();

    GroceryItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Leche',
        'current_stock' => 1,
        'target_stock' => 5,
    ]);

    GroceryItem::factory()->create([
        'user_id' => $user->id,
        'name' => 'Arroz',
        'current_stock' => 9,
        'target_stock' => 5,
    ]);

    $payload = json_decode(
        (new GroceryQueryTool($user))->handle(new Request(['low_stock' => true])),
        true,
    );

    expect($payload['low_stock_count'])->toBe(1)
        ->and($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['name'])->toBe('Leche');
});

it('filters grocery items by category', function () {
    $user = User::factory()->create();

    GroceryItem::factory()->create(['user_id' => $user->id, 'category' => 'Dairy']);
    GroceryItem::factory()->create(['user_id' => $user->id, 'category' => 'Meat']);

    $payload = json_decode(
        (new GroceryQueryTool($user))->handle(new Request(['category' => 'Dairy'])),
        true,
    );

    expect($payload['total_items'])->toBe(1)
        ->and($payload['items'][0]['category'])->toBe('Dairy');
});
