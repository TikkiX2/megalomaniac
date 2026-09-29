<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('blocks access to another users workout through web routes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    $this->actingAs($user)->getJson("/gym/workouts/{$workout->id}")->assertForbidden();
    $this->actingAs($user)->patchJson("/gym/workouts/{$workout->id}", ['notes' => 'x'])->assertForbidden();
    $this->actingAs($user)->postJson("/gym/workouts/{$workout->id}/exercises", ['exercise_id' => 1])->assertForbidden();
    $this->actingAs($user)->postJson("/gym/workout-exercises/{$workoutExercise->id}/sets", ['set_number' => 1])->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/workout-exercises/{$workoutExercise->id}")->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/workout-sets/{$set->id}")->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/workouts/{$workout->id}")->assertForbidden();
});

it('deletes the owners workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->deleteJson("/gym/workouts/{$workout->id}")->assertNoContent();

    $this->assertDatabaseMissing('workouts', ['id' => $workout->id]);
});

it('removes exercises and sets for the owner', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    $this->actingAs($user)->deleteJson("/gym/workout-sets/{$set->id}")->assertOk();
    expect($set->fresh())->toBeNull();

    $this->actingAs($user)->deleteJson("/gym/workout-exercises/{$workoutExercise->id}")->assertOk();
    expect($workoutExercise->fresh())->toBeNull();
});

it('marks pr sets and best weight in the active workout payload', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);
    $set = $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

    PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'workout_set_id' => $set->id,
        'type' => 'weight',
        'value' => 100,
        'weight' => 100,
        'reps' => 5,
    ]);

    $response = $this->actingAs($user)->getJson("/gym/workouts/{$workout->id}");

    $response->assertOk()
        ->assertJsonPath('exercises.0.sets.0.is_pr', true)
        ->assertJsonPath('exercises.0.best_weight', 100);
});

it('exposes the personal record timeline on the history page', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Press banca']);
    $workout = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);

    PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'type' => 'weight',
        'value' => 90,
        'weight' => 90,
        'reps' => 5,
    ]);

    $this->actingAs($user)->get('/fitness/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fitness/history')
            ->has('personalRecords', 1)
            ->where('personalRecords.0.exercise.name', 'Press banca'));
});

it('rejects a routine from another user when starting a workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->postJson('/gym/workouts', ['routine_id' => $routine->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['routine_id']);
});
