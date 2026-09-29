<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\Gym\PersonalRecordService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function recordService(): PersonalRecordService
{
    return app(PersonalRecordService::class);
}

function logSetFor(User $user, Exercise $exercise, array $data): WorkoutSet
{
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id]);

    return app(WorkoutSessionService::class)->logSet($user, $workoutExercise, $data);
}

it('records weight and one rep max PRs when logging a completed set', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $set = logSetFor($user, $exercise, ['weight' => 100, 'reps' => 5, 'completed' => true]);

    $records = PersonalRecord::where('user_id', $user->id)->where('exercise_id', $exercise->id)->get();

    expect($records)->toHaveCount(2)
        ->and($records->pluck('type')->all())->toContain('weight', 'one_rm')
        ->and($records->firstWhere('type', 'weight')->workout_set_id)->toBe($set->id)
        ->and((float) $records->firstWhere('type', 'one_rm')->value)->toBe(116.67);
});

it('does not record a PR when the set does not beat the best', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    logSetFor($user, $exercise, ['weight' => 100, 'reps' => 5, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 90, 'reps' => 5, 'completed' => true]);

    expect(PersonalRecord::count())->toBe(2);
});

it('tracks rep PRs per weight and ignores incomplete sets', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 8, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 10, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 12, 'completed' => false]);

    $repsRecords = PersonalRecord::where('type', 'reps')->get();

    // The first set at a weight only sets the rep baseline when it beats nothing;
    // the 10-rep set is the first true rep PR and the incomplete 12-rep set is ignored.
    expect($repsRecords)->toHaveCount(1)
        ->and((int) $repsRecords->first()->value)->toBe(10);
});

it('returns the timeline with exercises and annotations', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $set = logSetFor($user, $exercise, ['weight' => 120, 'reps' => 3, 'completed' => true]);

    $timeline = recordService()->timeline($user);

    expect($timeline)->not->toBeEmpty()
        ->and($timeline->first()->exercise->name)->toBe('Sentadilla');

    recordService()->annotateSets(collect([$set]));

    expect($set->is_pr)->toBeTrue()
        ->and(recordService()->bestWeightFor($user, $exercise))->toBe(120.0);
});
