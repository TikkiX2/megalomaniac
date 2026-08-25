<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('authenticated user can list workouts via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    Workout::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/workouts');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

test('unauthenticated user cannot access workouts API', function () {
    $response = $this->getJson('/api/v1/workouts');

    $response->assertUnauthorized();
});

test('authenticated user can create workout via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/workouts', [
            'started_at' => now()->toIso8601String(),
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'user_id', 'started_at']]);

    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
    ]);
});

test('authenticated user can create workout from routine via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercises = Exercise::factory()->count(2)->create();
    $routine->exercises()->attach($exercises->pluck('id'), ['order' => 1]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/workouts', [
            'routine_id' => $routine->id,
            'started_at' => now()->toIso8601String(),
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.routine_id', $routine->id);
});

test('authenticated user can show single workout via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/workouts/{$workout->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $workout->id);
});

test('authenticated user can update workout via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson("/api/v1/workouts/{$workout->id}", [
            'notes' => 'Updated notes',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'Updated notes');
});

test('authenticated user can delete workout via API', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson("/api/v1/workouts/{$workout->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
});

test('user cannot access another users workout via API', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $workout = Workout::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/workouts/{$workout->id}");

    $response->assertForbidden();
});

test('store workout validates required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/workouts', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['started_at']);
});

test('workout resource includes exercises and routine', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api-token')->plainTextToken;
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id]);
    $workoutExercise->sets()->create([
        'set_number' => 1,
        'weight' => 50,
        'reps' => 10,
        'completed' => true,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson("/api/v1/workouts/{$workout->id}");

    $response->assertOk()
        ->assertJsonPath('data.exercises.0.exercise.name', $exercise->name)
        ->assertJsonPath('data.exercises.0.sets.0.weight', '50.00')
        ->assertJsonPath('data.exercises.0.sets.0.reps', 10);
});
