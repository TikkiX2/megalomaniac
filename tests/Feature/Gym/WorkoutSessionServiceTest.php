<?php

use App\Exceptions\WorkoutAlreadyActiveException;
use App\Models\Exercise;
use App\Models\PersonalRecord;
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

it('throws with the active workout when starting a routine while one is active', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $active = Workout::factory()->create(['user_id' => $user->id]);

    try {
        sessions()->start($user, $routine->id);
        $this->fail('Expected WorkoutAlreadyActiveException');
    } catch (WorkoutAlreadyActiveException $e) {
        expect($e->workout->id)->toBe($active->id);
    }

    expect($active->refresh()->routine_id)->toBeNull();
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

it('rejects linking another users routine when updating a workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $routine = Routine::factory()->create();

    expect(fn () => sessions()->update($user, $workout, ['routine_id' => $routine->id]))
        ->toThrow(ModelNotFoundException::class);

    expect($workout->refresh()->routine_id)->toBeNull();
});

it('links an owned routine when updating a workout', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $routine = Routine::factory()->create(['user_id' => $user->id]);

    $updated = sessions()->update($user, $workout, ['routine_id' => $routine->id]);

    expect($updated->routine_id)->toBe($routine->id);
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

it('repeats a previous workout copying exercises and sets', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $source = Workout::factory()->create([
        'user_id' => $user->id, 'routine_id' => $routine->id,
        'ended_at' => now(), 'notes' => 'dia de pierna',
    ]);
    $we = $source->exercises()->create(['exercise_id' => $exercise->id, 'order' => 2]);
    $we->sets()->create(['set_number' => 1, 'weight' => 60, 'reps' => 10, 'rpe' => 8, 'completed' => true]);
    $we->sets()->create(['set_number' => 2, 'weight' => 65, 'reps' => 8, 'rpe' => 9, 'completed' => true]);

    $copy = sessions()->repeat($user, $source);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->routine_id)->toBe($routine->id)
        ->and($copy->notes)->toBe('dia de pierna')
        ->and($copy->ended_at)->toBeNull();

    $newWe = $copy->exercises()->first();
    expect($newWe->exercise_id)->toBe($exercise->id)
        ->and($newWe->order)->toBe(2);

    $sets = $newWe->sets()->orderBy('set_number')->get();
    expect($sets)->toHaveCount(2)
        ->and($sets[0]->set_number)->toBe(1)
        ->and((float) $sets[0]->weight)->toBe(60.0)
        ->and($sets[0]->reps)->toBe(10)
        ->and((float) $sets[0]->rpe)->toBe(8.0)
        ->and($sets[0]->completed)->toBeFalse()
        ->and($sets[1]->set_number)->toBe(2)
        ->and((float) $sets[1]->weight)->toBe(65.0);
});

it('repeats an empty quick session without errors', function () {
    $user = User::factory()->create();
    $source = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);

    $copy = sessions()->repeat($user, $source);

    expect($copy->exercises)->toHaveCount(0);
});

it('rejects repeating another users workout', function () {
    $user = User::factory()->create();
    $other = Workout::factory()->create();

    expect(fn () => sessions()->repeat($user, $other))
        ->toThrow(AuthorizationException::class);
});

it('rejects repeating an active workout as the source', function () {
    $user = User::factory()->create();
    $active = Workout::factory()->create(['user_id' => $user->id]);

    expect(fn () => sessions()->repeat($user, $active))
        ->toThrow(InvalidArgumentException::class, 'Cannot repeat an active workout.');
});

it('rejects repeating a finished workout while another is active', function () {
    $user = User::factory()->create();
    $finished = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);
    $active = Workout::factory()->create(['user_id' => $user->id]);

    try {
        sessions()->repeat($user, $finished);
        $this->fail('Expected WorkoutAlreadyActiveException');
    } catch (WorkoutAlreadyActiveException $e) {
        expect($e->workout->id)->toBe($active->id);
    }

    expect(Workout::where('user_id', $user->id)->count())->toBe(2);
});

it('logs a past workout already finished at the chosen date', function () {
    $user = User::factory()->create();
    $date = now()->subDays(3)->setTime(18, 30);

    $workout = sessions()->logPast($user, null, $date->toIso8601String(), 'fue duro');

    expect($workout->started_at->toDateTimeString())->toBe($date->toDateTimeString())
        ->and($workout->ended_at->toDateTimeString())->toBe($date->toDateTimeString())
        ->and($workout->notes)->toBe('fue duro')
        ->and(PersonalRecord::count())->toBe(0);
});

it('logs a past workout copying the routine template', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 2, 'target_weight' => 60, 'target_reps' => '8']);

    $workout = sessions()->logPast($user, $routine->id, now()->subDays(2)->toIso8601String());

    expect($workout->exercises)->toHaveCount(1)
        ->and($workout->exercises->first()->sets()->count())->toBe(2)
        ->and($workout->ended_at)->not->toBeNull();
});

it('rejects logging a past workout with another users routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    expect(fn () => sessions()->logPast($user, $routine->id, now()->toIso8601String()))
        ->toThrow(ModelNotFoundException::class);
});

it('does not copy non-numeric template values into workout sets', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    // El usuario usa bandas elásticas: "Banda" es texto válido en el card de la
    // rutina, pero workout_sets.weight/reps son columnas numeric en postgres.
    $routine->exercises()->attach($exercise->id, [
        'order' => 1,
        'target_sets' => 2,
        'target_weight' => 'Banda',
        'target_reps' => '15-20',
    ]);

    $workout = sessions()->logPast($user, $routine->id, now()->subDay()->toIso8601String());

    $sets = $workout->exercises->first()->sets()->orderBy('set_number')->get();

    expect($sets)->toHaveCount(2)
        ->and($sets[0]->weight)->toBeNull()
        ->and($sets[0]->reps)->toBeNull()
        ->and($sets[1]->weight)->toBeNull()
        ->and($sets[1]->reps)->toBeNull();
});

it('builds per-session progression for an exercise', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $older = Workout::factory()->create([
        'user_id' => $user->id,
        'started_at' => now()->subDays(10),
        'ended_at' => now()->subDays(10)->addHour(),
    ]);
    $olderWe = $older->exercises()->create(['exercise_id' => $exercise->id]);
    $olderWe->sets()->create(['set_number' => 1, 'weight' => 60, 'reps' => 10, 'completed' => true]);

    $finished = Workout::factory()->create([
        'user_id' => $user->id,
        'started_at' => now()->subDays(2),
        'ended_at' => now()->subDays(2)->addHour(),
    ]);
    $we = $finished->exercises()->create(['exercise_id' => $exercise->id]);
    $we->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);
    $we->sets()->create(['set_number' => 2, 'weight' => 80, 'reps' => 12, 'completed' => true]);

    $open = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
    $openWe = $open->exercises()->create(['exercise_id' => $exercise->id]);
    $openWe->sets()->create(['set_number' => 1, 'weight' => 999, 'reps' => 5, 'completed' => true]);

    $rows = sessions()->progressionFor($user, $exercise);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['workout_id'])->toBe($older->id)
        ->and($rows[0]['date'])->toBe($older->started_at->toDateString())
        ->and($rows[0]['best_weight'])->toBe(60.0)
        ->and($rows[1]['workout_id'])->toBe($finished->id)
        ->and($rows[1]['date'])->toBe($finished->started_at->toDateString())
        ->and($rows[1]['best_weight'])->toBe(100.0)
        ->and($rows[1]['best_1rm'])->toBe(116.67)
        ->and($rows[1]['volume'])->toBe(1460.0)
        ->and($rows[1]['total_reps'])->toBe(17)
        ->and($rows[1]['completed_sets'])->toBe(2);
});
