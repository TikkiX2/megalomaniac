<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sessions(): WorkoutSessionService
{
    return app(WorkoutSessionService::class);
}

it('starts a workout and returns the active one afterwards', function () {
    $user = User::factory()->create();

    $workout = sessions()->start($user, null, now()->toIso8601String());

    expect($workout->user_id)->toBe($user->id)
        ->and($workout->ended_at)->toBeNull()
        ->and(sessions()->activeFor($user)->id)->toBe($workout->id)
        ->and(sessions()->start($user)->id)->toBe($workout->id);
});

it('copies the routine template and prefills sets from previous workouts', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach($exercise->id, [
        'order' => 1,
        'target_sets' => 2,
        'target_weight' => 60,
        'target_reps' => '8',
    ]);

    // Previous finished workout with a logged set.
    $previous = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);
    $previousExercise = $previous->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);
    $previousExercise->sets()->create(['set_number' => 1, 'weight' => 55, 'reps' => 9, 'completed' => true]);

    $workout = sessions()->start($user, $routine->id);

    expect($workout->exercises)->toHaveCount(1);

    $sets = $workout->exercises->first()->sets()->orderBy('set_number')->get();

    expect($sets)->toHaveCount(2)
        ->and($sets[0]->set_number)->toBe(1)
        ->and((float) $sets[0]->weight)->toBe(55.0)
        ->and($sets[0]->reps)->toBe(9)
        ->and((float) $sets[1]->weight)->toBe(60.0)
        ->and((float) $sets[1]->reps)->toBe(8.0)
        ->and($sets[1]->completed)->toBeFalse();
});

it('rejects starting from another users routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    expect(fn () => sessions()->start($user, $routine->id))
        ->toThrow(ModelNotFoundException::class);
});

it('adds an exercise by name, creating it once', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $first = sessions()->addExercise($user, $workout, null, 'Face pull');
    $second = sessions()->addExercise($user, $workout, null, 'face pull');

    expect($first->exercise_id)->toBe($second->exercise_id)
        ->and(Exercise::where('name', 'Face pull')->count())->toBe(1)
        ->and($first->order)->toBe(1)
        ->and($second->order)->toBe(2);
});

it('logs sets with automatic numbering and updates existing ones', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $first = sessions()->logSet($user, $workoutExercise, ['weight' => 40, 'reps' => 10]);
    $second = sessions()->logSet($user, $workoutExercise, ['weight' => 45, 'reps' => 8]);

    expect($first->set_number)->toBe(1)
        ->and($second->set_number)->toBe(2);

    $updated = sessions()->logSet($user, $workoutExercise, ['set_number' => 1, 'weight' => 42]);

    expect($updated->id)->toBe($first->id)
        ->and((float) $updated->refresh()->weight)->toBe(42.0);
});

it('blocks writes on workouts that belong to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    expect(fn () => sessions()->addExercise($user, $workout, 1))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->logSet($user, $workoutExercise, []))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->removeSet($user, $set))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->delete($user, $workout))
        ->toThrow(AuthorizationException::class);
});

it('finishes, removes and deletes workout parts', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    sessions()->removeSet($user, $set);
    expect($set->exists)->toBeFalse();

    sessions()->removeExercise($user, $workoutExercise);
    expect($workoutExercise->exists)->toBeFalse();

    sessions()->finish($user, $workout, null, 'done');
    expect($workout->refresh()->ended_at)->not->toBeNull()
        ->and($workout->notes)->toBe('done');

    sessions()->delete($user, $workout);
    expect($workout->exists)->toBeFalse();
});
