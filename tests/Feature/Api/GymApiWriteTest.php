<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tokenFor(User $user): string
{
    return $user->createToken('api-token')->plainTextToken;
}

it('copies the routine template when creating a workout via API', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 3]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', ['routine_id' => $routine->id]);

    $response->assertCreated()
        ->assertJsonCount(1, 'data.exercises')
        ->assertJsonCount(3, 'data.exercises.0.sets');
});

it('returns the active workout instead of creating a second one', function () {
    $user = User::factory()->create();
    Workout::factory()->create(['user_id' => $user->id, 'ended_at' => null]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', [])
        ->assertOk();

    expect(Workout::where('user_id', $user->id)->count())->toBe(1);
});

it('adds an exercise by name via API', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workouts/{$workout->id}/exercises", ['exercise_name' => 'Remo Pendlay']);

    $response->assertCreated()
        ->assertJsonPath('data.exercise.name', 'Remo Pendlay');

    expect(Exercise::where('name', 'Remo Pendlay')->exists())->toBeTrue();
});

it('logs sets with auto numbering via API', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workout-exercises/{$workoutExercise->id}/sets", ['weight' => 60, 'reps' => 8, 'completed' => true]);

    $response->assertOk()
        ->assertJsonPath('data.set_number', 1);
});

it('rejects a foreign routine id when creating a workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', ['routine_id' => $routine->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['routine_id']);
});

it('blocks detail writes on another users workout', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workouts/{$workout->id}/exercises", ['exercise_name' => 'X'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workout-exercises/{$workoutExercise->id}/sets", ['weight' => 1])
        ->assertForbidden();
});

it('updates and deletes own routines via API', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $foreign = Routine::factory()->create(['user_id' => $other->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->patchJson("/api/v1/routines/{$routine->id}", ['name' => 'Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated');

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->patchJson("/api/v1/routines/{$foreign->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->deleteJson("/api/v1/routines/{$routine->id}")
        ->assertNoContent();

    expect(Routine::whereKey($routine->id)->exists())->toBeFalse();
});
