<?php

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can list exercises via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Exercise::factory()->count(3)->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/exercises');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('unauthenticated user cannot access exercises API', function () {
    $response = $this->getJson('/api/v1/exercises');

    $response->assertUnauthorized();
});

test('authenticated user can filter exercises by muscle_group', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Exercise::factory()->count(2)->create(['muscle_group' => 'chest']);
    Exercise::factory()->count(1)->create(['muscle_group' => 'legs']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/exercises?muscle_group=chest');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

test('authenticated user can search exercises by name', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Exercise::factory()->create(['name' => 'Bench Press']);
    Exercise::factory()->create(['name' => 'Squat']);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/exercises?search=Bench');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Bench Press');
});

test('authenticated user can create exercise via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/exercises', [
            'name' => 'Deadlift',
            'muscle_group' => 'back',
            'type' => 'strength',
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'muscle_group', 'type']]);

    $this->assertDatabaseHas('exercises', [
        'name' => 'Deadlift',
        'muscle_group' => 'back',
    ]);
});

test('store exercise validates required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/exercises', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});
