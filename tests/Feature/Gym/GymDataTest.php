<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces one set per set_number inside a workout exercise', function () {
    $workoutExercise = WorkoutExercise::factory()->create();

    WorkoutSet::factory()->create([
        'workout_exercise_id' => $workoutExercise->id,
        'set_number' => 1,
    ]);

    expect(fn () => WorkoutSet::factory()->create([
        'workout_exercise_id' => $workoutExercise->id,
        'set_number' => 1,
    ]))->toThrow(QueryException::class);
});

it('seeds a canonical exercise library', function () {
    $this->seed(ExerciseSeeder::class);

    expect(Exercise::count())->toBeGreaterThanOrEqual(30);
    expect(Exercise::where('name', 'Press banca')->exists())->toBeTrue();
});

it('casts workout set reps to integer', function () {
    $set = WorkoutSet::factory()->create(['reps' => '10']);

    expect($set->refresh()->reps)->toBeInt()->toBe(10);
});

it('stores personal records with exercise relation', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 80, 'reps' => 5, 'completed' => true]);

    $record = PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $workoutExercise->exercise_id,
        'workout_set_id' => $set->id,
        'type' => 'weight',
        'value' => 80,
        'weight' => 80,
        'reps' => 5,
    ]);

    expect($record->exercise)->toBeInstanceOf(Exercise::class)
        ->and($record->workoutSet->id)->toBe($set->id)
        ->and((float) $record->value)->toBe(80.0);
});
