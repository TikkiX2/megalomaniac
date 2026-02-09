<?php

use App\Models\Supplement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can list their supplements', function () {
    $user = User::factory()->create();
    Supplement::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get('/fitness/supplements');

    $response->assertStatus(200);
});

test('user can create a supplement', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/supplements/items', [
        'name' => 'Whey Protein',
        'brand' => 'Optimum Nutrition',
        'dosage_amount' => '1 scoop',
        'frequency' => 'Daily',
        'stock_quantity' => 20,
        'low_stock_threshold' => 5,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('supplements', [
        'name' => 'Whey Protein',
        'user_id' => $user->id,
    ]);
});

test('user can update a supplement', function () {
    $user = User::factory()->create();
    $supplement = Supplement::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $response = $this->actingAs($user)->put("/supplements/items/{$supplement->id}", [
        'name' => 'New Name',
        'stock_quantity' => 10,
        'low_stock_threshold' => 2,
    ]);

    $response->assertRedirect();
    expect($supplement->fresh()->name)->toBe('New Name');
});

test('user can delete a supplement', function () {
    $user = User::factory()->create();
    $supplement = Supplement::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete("/supplements/items/{$supplement->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('supplements', ['id' => $supplement->id]);
});

test('user can log supplement intake and stock decrements', function () {
    $user = User::factory()->create();
    $supplement = Supplement::factory()->create([
        'user_id' => $user->id,
        'stock_quantity' => 10,
    ]);

    $response = $this->actingAs($user)->post("/supplements/{$supplement->id}/log");

    $response->assertRedirect();
    expect($supplement->fresh()->stock_quantity)->toBe(9);
    $this->assertDatabaseHas('supplement_logs', [
        'supplement_id' => $supplement->id,
        'user_id' => $user->id,
    ]);
});
