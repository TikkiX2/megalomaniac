<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Models\HealthSymptom;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Health\HealthGymCrossoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new HealthGymCrossoverService;
    $this->user = User::factory()->create();
});

it('records and retrieves gym crossover events', function () {
    $symptom = HealthSymptom::factory()->create(['user_id' => $this->user->id]);
    $workout = Workout::factory()->create(['user_id' => $this->user->id]);
    $exercise = WorkoutExercise::factory()->create(['workout_id' => $workout->id]);
    $set = WorkoutSet::factory()->create(['workout_exercise_id' => $exercise->id]);

    $crossover = $this->service->recordCrossover($this->user, [
        'symptom_id' => $symptom->id,
        'workout_set_id' => $set->id,
        'occurred_at' => now()->subDays(3)->toDateTimeString(),
        'notes' => 'Correlación observada',
    ]);

    expect($crossover->user_id)->toBe($this->user->id);
    expect($crossover->symptom_id)->toBe($symptom->id);
    expect($crossover->workout_set_id)->toBe($set->id);

    $crossovers = $this->service->getCrossovers($this->user);
    expect($crossovers)->toHaveCount(1);
    expect($crossovers->first()->id)->toBe($crossover->id);
});

it('filters crossovers by symptom and workout set', function () {
    $symptom1 = HealthSymptom::factory()->create(['user_id' => $this->user->id]);
    $symptom2 = HealthSymptom::factory()->create(['user_id' => $this->user->id]);
    $workout = Workout::factory()->create(['user_id' => $this->user->id]);
    $exercise = WorkoutExercise::factory()->create(['workout_id' => $workout->id]);
    $set = WorkoutSet::factory()->create(['workout_exercise_id' => $exercise->id]);

    $this->service->recordCrossover($this->user, [
        'symptom_id' => $symptom1->id,
        'workout_set_id' => $set->id,
        'occurred_at' => now()->subDays(3)->toDateTimeString(),
    ]);

    $this->service->recordCrossover($this->user, [
        'symptom_id' => $symptom2->id,
        'workout_set_id' => $set->id,
        'occurred_at' => now()->subDays(2)->toDateTimeString(),
    ]);

    $filteredBySymptom = $this->service->getCrossovers($this->user, $symptom1->id);
    expect($filteredBySymptom)->toHaveCount(1);
    expect($filteredBySymptom->first()->symptom_id)->toBe($symptom1->id);

    $filteredByWorkout = $this->service->getCrossovers($this->user, null, $set->id);
    expect($filteredByWorkout)->toHaveCount(2);
});

it('returns statistics about crossovers', function () {
    $symptom = HealthSymptom::factory()->create(['user_id' => $this->user->id, 'symptom' => 'Dolor lumbar']);
    $workout = Workout::factory()->create(['user_id' => $this->user->id]);
    $exercise1 = WorkoutExercise::factory()->create(['workout_id' => $workout->id]);
    $exercise2 = WorkoutExercise::factory()->create(['workout_id' => $workout->id]);
    $set1 = WorkoutSet::factory()->create(['workout_exercise_id' => $exercise1->id]);
    $set2 = WorkoutSet::factory()->create(['workout_exercise_id' => $exercise2->id]);

    $this->service->recordCrossover($this->user, [
        'symptom_id' => $symptom->id,
        'workout_set_id' => $set1->id,
        'occurred_at' => now()->subDays(3)->toDateTimeString(),
        'notes' => 'First',
    ]);

    $this->service->recordCrossover($this->user, [
        'symptom_id' => $symptom->id,
        'workout_set_id' => $set2->id,
        'occurred_at' => now()->subDays(2)->toDateTimeString(),
        'notes' => 'Second',
    ]);

    $stats = $this->service->getStatistics($this->user);

    expect($stats['total'])->toBe(2);
    expect($stats['by_symptom'])->toHaveKey('Dolor lumbar');
    expect($stats['by_workout'])->toHaveCount(2);
});
