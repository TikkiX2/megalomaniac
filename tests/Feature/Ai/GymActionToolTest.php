<?php

use App\Ai\Tools\GymActionTool;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function gymTool(User $user): GymActionTool
{
    return new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
}

it('creates a workout from a routine and returns workout exercise ids', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach(
        Exercise::factory()->create()->id,
        ['order' => 1, 'target_sets' => 2],
    );

    $result = gymTool($user)->handle(new Request([
        'action' => 'create_workout',
        'routine_id' => $routine->id,
    ]));

    $data = json_decode($result, true);

    expect($data['success'])->toBeTrue()
        ->and($data['workout']['exercises'])->toHaveCount(1)
        ->and($data['workout']['exercises'][0]['id'])->toBeInt()
        ->and($data['workout']['exercises'][0]['sets'])->toHaveCount(2);
});

it('adds exercises by name, logging sets and detecting prs', function () {
    $user = User::factory()->create();

    gymTool($user)->handle(new Request(['action' => 'create_workout']));

    $workout = $user->workouts()->firstOrFail();

    $added = json_decode(gymTool($user)->handle(new Request([
        'action' => 'add_exercise',
        'workout_id' => $workout->id,
        'exercise_name' => 'Press banca',
    ])), true);

    expect($added['success'])->toBeTrue();

    $workoutExerciseId = $added['workout_exercise']['id'];

    $set = json_decode(gymTool($user)->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExerciseId,
        'weight' => 100,
        'reps' => 5,
        'completed' => true,
    ])), true);

    expect($set['success'])->toBeTrue()
        ->and($set['set']['set_number'])->toBe(1)
        ->and($set['set']['is_pr'])->toBeTrue();

    $second = json_decode(gymTool($user)->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExerciseId,
        'weight' => 90,
        'reps' => 5,
    ])), true);

    expect($second['set']['set_number'])->toBe(2);
});

it('creates a routine with exercises in one call and updates it', function () {
    $user = User::factory()->create();

    $created = json_decode(gymTool($user)->handle(new Request([
        'action' => 'create_routine',
        'name' => 'Piernas',
        'focus' => 'Legs',
        'exercises' => [
            ['name' => 'Sentadilla', 'target_sets' => 5, 'target_reps' => '5'],
            ['name' => 'Prensa', 'target_sets' => 3],
        ],
    ])), true);

    expect($created['success'])->toBeTrue()
        ->and($created['routine']['exercises'])->toHaveCount(2);

    $routineId = $created['routine']['id'];

    $updated = json_decode(gymTool($user)->handle(new Request([
        'action' => 'update_routine',
        'routine_id' => $routineId,
        'name' => 'Piernas v2',
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and($updated['routine']['name'])->toBe('Piernas v2');
});

it('appends an exercise to an existing routine', function () {
    $user = User::factory()->create();

    $created = json_decode(gymTool($user)->handle(new Request([
        'action' => 'create_routine',
        'name' => 'Piernas',
        'exercises' => [
            ['name' => 'Sentadilla', 'target_sets' => 5],
        ],
    ])), true);

    $appended = json_decode(gymTool($user)->handle(new Request([
        'action' => 'add_routine_exercise',
        'routine_id' => $created['routine']['id'],
        'exercise_name' => 'Prensa',
        'target_sets' => 3,
    ])), true);

    expect($appended['success'])->toBeTrue()
        ->and($appended['routine']['exercises'])->toHaveCount(2)
        ->and($appended['routine']['exercises'][1]['name'])->toBe('Prensa')
        ->and($appended['routine']['exercises'][1]['pivot']['target_sets'])->toBe(3);
});

it('returns readable errors and requires approval', function () {
    $user = User::factory()->create();

    $result = json_decode(gymTool($user)->handle(new Request([
        'action' => 'add_exercise',
        'workout_id' => 999,
        'exercise_name' => 'X',
    ])), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('not found');

    expect(gymTool($user)->needsApproval(new Request(['action' => 'log_set'])))
        ->toBeInstanceOf(Approval::class);
});

it('updates, prunes and deletes workouts and routines', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id, 'notes' => 'original']);
    $exercise = Exercise::factory()->create(['name' => 'Remo']);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 40, 'reps' => 10, 'completed' => true]);
    $routine = Routine::factory()->create(['user_id' => $user->id]);

    $updated = json_decode((string) gymTool($user)->handle(new Request([
        'action' => 'update_workout',
        'workout_id' => $workout->id,
        'notes' => 'ajustado',
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and($workout->fresh()->notes)->toBe('ajustado');

    $removedSet = json_decode((string) gymTool($user)->handle(new Request([
        'action' => 'remove_set',
        'workout_set_id' => $set->id,
    ])), true);

    expect($removedSet['success'])->toBeTrue()
        ->and($workoutExercise->sets()->count())->toBe(0);

    $removedExercise = json_decode((string) gymTool($user)->handle(new Request([
        'action' => 'remove_exercise',
        'workout_exercise_id' => $workoutExercise->id,
    ])), true);

    expect($removedExercise['success'])->toBeTrue()
        ->and($workout->exercises()->count())->toBe(0);

    $deletedRoutine = json_decode((string) gymTool($user)->handle(new Request([
        'action' => 'delete_routine',
        'routine_id' => $routine->id,
    ])), true);

    expect($deletedRoutine['success'])->toBeTrue()
        ->and(Routine::find($routine->id))->toBeNull();

    $deletedWorkout = json_decode((string) gymTool($user)->handle(new Request([
        'action' => 'delete_workout',
        'workout_id' => $workout->id,
    ])), true);

    expect($deletedWorkout['success'])->toBeTrue()
        ->and(Workout::find($workout->id))->toBeNull();
});
