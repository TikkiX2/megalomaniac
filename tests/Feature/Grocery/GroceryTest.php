<?php

use App\Models\GroceryItem;
use App\Models\User;

test('grocery page can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('fitness.groceries'));

    $response->assertStatus(200);
});

test('user can create grocery item', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('grocery.items.store'), [
        'name' => 'Test Item',
        'category' => 'Test Category',
        'quantity' => 2,
        'unit' => 'kg',
        'price' => 10.50,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('grocery_items', [
        'user_id' => $user->id,
        'name' => 'Test Item',
        'quantity' => 2,
    ]);
});

test('user can toggle grocery item', function () {
    $user = User::factory()->create();
    $item = GroceryItem::factory()->create([
        'user_id' => $user->id,
        'is_purchased' => false,
    ]);

    $response = $this->actingAs($user)->patch("/grocery/{$item->id}/toggle");

    $response->assertRedirect();
    $this->assertTrue($item->refresh()->is_purchased);
});

test('user can delete grocery item', function () {
    $user = User::factory()->create();
    $item = GroceryItem::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete(route('grocery.items.destroy', $item->id));

    $response->assertRedirect();
    $this->assertDatabaseMissing('grocery_items', ['id' => $item->id]);
});
