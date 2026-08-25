<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can list routines via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Routine::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/routines');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('unauthenticated user cannot access routines API', function () {
    $response = $this->getJson('/api/v1/routines');

    $response->assertUnauthorized();
});

test('user cannot see other users routines', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Routine::factory()->count(2)->create(['user_id' => $user->id]);
    Routine::factory()->count(3)->create(['user_id' => $otherUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/routines');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('routine resource includes exercises', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $routine->exercises()->attach($exercise->id, ['order' => 1]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/routines');

    $response->assertOk()
        ->assertJsonPath('data.0.name', $routine->name)
        ->assertJsonPath('data.0.exercises', fn (array $exercises) => count($exercises) === 1);
});

test('authenticated user can create routine via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/routines', [
            'name' => 'Push Day',
            'focus' => 'Chest, Shoulders',
            'status' => 'active',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'focus', 'status']]);

    $this->assertDatabaseHas('routines', [
        'user_id' => $user->id,
        'name' => 'Push Day',
    ]);
});

test('authenticated user can create routine with exercises via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $exercises = Exercise::factory()->count(3)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/routines', [
            'name' => 'Leg Day',
            'focus' => 'Quads, Hamstrings',
            'exercise_ids' => $exercises->pluck('id')->toArray(),
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.exercises', fn (array $exercises) => count($exercises) === 3);

    $routine = Routine::where('name', 'Leg Day')->first();
    $this->assertNotNull($routine);
    $this->assertCount(3, $routine->exercises);
});

test('store routine validates required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/routines', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('store routine validates status enum', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/routines', [
            'name' => 'Test',
            'status' => 'invalid',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
