<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can start a workout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/gym/workouts', [
        'started_at' => now()->toIso8601String(),
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
        'ended_at' => null,
    ]);
});

test('user can start a workout from a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercises = Exercise::factory()->count(3)->create();
    $routine->exercises()->attach($exercises->pluck('id'), ['order' => 1]);

    $response = $this->actingAs($user)->postJson('/gym/workouts', [
        'routine_id' => $routine->id,
        'started_at' => now()->toIso8601String(),
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('workouts', [
        'user_id' => $user->id,
        'routine_id' => $routine->id,
    ]);

    $workout = Workout::first();
    expect($workout->exercises)->toHaveCount(3);
});

test('user can add an exercise to a workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();

    $response = $this->actingAs($user)->postJson("/gym/workouts/{$workout->id}/exercises", [
        'exercise_id' => $exercise->id,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('workout_exercises', [
        'workout_id' => $workout->id,
        'exercise_id' => $exercise->id,
    ]);
});

test('user can log a set', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id]);

    $response = $this->actingAs($user)->postJson("/gym/workout-exercises/{$workoutExercise->id}/sets", [
        'set_number' => 1,
        'weight' => 50,
        'reps' => 10,
        'rpe' => 8,
        'completed' => true,
    ]);

    $response->assertStatus(200);
    $this->assertDatabaseHas('workout_sets', [
        'workout_exercise_id' => $workoutExercise->id,
        'weight' => 50,
        'reps' => 10,
        'completed' => true,
    ]);
});

test('user can finish a workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->patchJson("/gym/workouts/{$workout->id}", [
        'ended_at' => now()->toIso8601String(),
    ]);

    $response->assertStatus(200);
    $this->assertNotNull($workout->refresh()->ended_at);
});
