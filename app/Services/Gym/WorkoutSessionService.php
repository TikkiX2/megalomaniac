<?php

namespace App\Services\Gym;

use App\Exceptions\WorkoutAlreadyActiveException;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkoutSessionService
{
    public function __construct(
        protected ExerciseResolver $resolver,
        protected PersonalRecordService $records,
    ) {}

    public function activeFor(User $user): ?Workout
    {
        return $user->workouts()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    public function start(User $user, ?int $routineId = null, ?string $startedAt = null, ?string $notes = null): Workout
    {
        if ($active = $this->activeFor($user)) {
            if ($routineId !== null) {
                throw new WorkoutAlreadyActiveException($active);
            }

            return $active;
        }

        $routine = null;

        if ($routineId) {
            $routine = Routine::with('exercises')
                ->where('user_id', $user->id)
                ->findOrFail($routineId);
        }

        $workout = $user->workouts()->create([
            'routine_id' => $routineId,
            'started_at' => $startedAt ?? now(),
            'notes' => $notes,
        ]);

        if ($routine) {
            $this->copyRoutineTemplate($user, $workout, $routine);
        }

        return $workout;
    }

    public function repeat(User $user, Workout $source): Workout
    {
        $this->assertOwnsWorkout($user, $source);

        if ($source->ended_at === null) {
            throw new InvalidArgumentException('Cannot repeat an active workout.');
        }

        if ($active = $this->activeFor($user)) {
            throw new WorkoutAlreadyActiveException($active);
        }

        $workout = $user->workouts()->create([
            'routine_id' => $source->routine_id,
            'started_at' => now(),
            'notes' => $source->notes,
        ]);

        foreach ($source->exercises()->with('sets')->get() as $sourceExercise) {
            $workoutExercise = $workout->exercises()->create([
                'exercise_id' => $sourceExercise->exercise_id,
                'order' => $sourceExercise->order,
            ]);

            foreach ($sourceExercise->sets as $sourceSet) {
                $workoutExercise->sets()->create([
                    'set_number' => $sourceSet->set_number,
                    'weight' => $this->sanitizeSetValue($sourceSet->weight),
                    'reps' => $this->sanitizeSetValue($sourceSet->reps),
                    'rpe' => $this->sanitizeSetValue($sourceSet->rpe),
                    'completed' => false,
                ]);
            }
        }

        return $workout;
    }

    public function logPast(User $user, ?int $routineId, string $startedAt, ?string $notes = null): Workout
    {
        $routine = null;

        if ($routineId) {
            $routine = Routine::with('exercises')
                ->where('user_id', $user->id)
                ->findOrFail($routineId);
        }

        $workout = $user->workouts()->create([
            'routine_id' => $routineId,
            'started_at' => $startedAt,
            'ended_at' => $startedAt,
            'notes' => $notes,
        ]);

        if ($routine) {
            $this->copyRoutineTemplate($user, $workout, $routine);
        }

        return $workout;
    }

    public function addExercise(User $user, Workout $workout, ?int $exerciseId = null, ?string $exerciseName = null): WorkoutExercise
    {
        $this->assertOwnsWorkout($user, $workout);

        $exercise = $this->resolver->resolve($exerciseId, $exerciseName);

        $order = ($workout->exercises()->max('order') ?? 0) + 1;

        return $workout->exercises()->create([
            'exercise_id' => $exercise->id,
            'order' => $order,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logSet(User $user, WorkoutExercise $workoutExercise, array $data): WorkoutSet
    {
        $this->assertOwnsWorkoutExercise($user, $workoutExercise);

        $setNumber = $data['set_number']
            ?? (($workoutExercise->sets()->max('set_number') ?? 0) + 1);

        $set = $workoutExercise->sets()->updateOrCreate(
            ['set_number' => $setNumber],
            Arr::only($data, ['weight', 'reps', 'rpe', 'completed']),
        );

        if ($set->completed) {
            $this->records->evaluate($user, $set);
        }

        return $set;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, Workout $workout, array $attributes): Workout
    {
        $this->assertOwnsWorkout($user, $workout);

        $routineId = $attributes['routine_id'] ?? null;

        if ($routineId !== null) {
            Routine::where('user_id', $user->id)->findOrFail($routineId);
        }

        $workout->update(Arr::only($attributes, ['routine_id', 'started_at', 'ended_at', 'notes']));

        return $workout;
    }

    public function finish(User $user, Workout $workout, ?string $endedAt = null, ?string $notes = null): Workout
    {
        $attributes = ['ended_at' => $endedAt ?? now()];

        if ($notes !== null) {
            $attributes['notes'] = $notes;
        }

        return $this->update($user, $workout, $attributes);
    }

    public function removeExercise(User $user, WorkoutExercise $workoutExercise): void
    {
        $this->assertOwnsWorkoutExercise($user, $workoutExercise);

        $workoutExercise->delete();
    }

    public function removeSet(User $user, WorkoutSet $set): void
    {
        $this->assertOwnsWorkoutExercise($user, $set->workoutExercise);

        $set->delete();
    }

    public function delete(User $user, Workout $workout): void
    {
        $this->assertOwnsWorkout($user, $workout);

        $workout->delete();
    }

    /**
     * Build a per-session progression summary for an exercise, one entry per
     * finished workout (workouts still open are ignored), ordered by started_at.
     *
     * @return Collection<int, array{
     *     workout_id: int,
     *     date: string,
     *     best_weight: float,
     *     best_1rm: float,
     *     volume: float,
     *     total_reps: int,
     *     completed_sets: int,
     * }>
     */
    public function progressionFor(User $user, Exercise $exercise): Collection
    {
        $sets = WorkoutSet::query()
            ->join('workout_exercises', 'workout_sets.workout_exercise_id', '=', 'workout_exercises.id')
            ->join('workouts', 'workout_exercises.workout_id', '=', 'workouts.id')
            ->where('workout_exercises.exercise_id', $exercise->id)
            ->where('workouts.user_id', $user->id)
            ->whereNotNull('workouts.ended_at')
            ->orderBy('workouts.started_at')
            ->get([
                'workouts.id as workout_id',
                'workouts.started_at',
                'workout_sets.weight',
                'workout_sets.reps',
                'workout_sets.completed',
            ]);

        return $sets
            ->groupBy('workout_id')
            ->map(function (Collection $workoutSets, int $workoutId): array {
                return [
                    'workout_id' => (int) $workoutId,
                    'date' => Carbon::parse($workoutSets->first()->started_at)->toDateString(),
                    'best_weight' => (float) ($workoutSets->max(
                        fn (WorkoutSet $set) => is_numeric($set->weight) && (float) $set->weight > 0
                            ? (float) $set->weight
                            : null,
                    ) ?? 0),
                    'best_1rm' => (float) ($workoutSets->max(
                        fn (WorkoutSet $set) => is_numeric($set->weight) && (float) $set->weight > 0
                            && is_numeric($set->reps) && (int) $set->reps > 0
                            ? round((float) $set->weight * (1 + (int) $set->reps / 30), 2)
                            : null,
                    ) ?? 0),
                    'volume' => (float) $workoutSets->sum(
                        fn (WorkoutSet $set) => is_numeric($set->weight) && (float) $set->weight > 0
                            && is_numeric($set->reps) && (int) $set->reps > 0
                            ? (float) $set->weight * (int) $set->reps
                            : 0,
                    ),
                    'total_reps' => (int) $workoutSets->sum(
                        fn (WorkoutSet $set) => is_numeric($set->reps) && (int) $set->reps > 0
                            ? (int) $set->reps
                            : 0,
                    ),
                    'completed_sets' => (int) $workoutSets->where('completed')->count(),
                ];
            })
            ->values();
    }

    private function copyRoutineTemplate(User $user, Workout $workout, Routine $routine): void
    {
        foreach ($routine->exercises as $exercise) {
            $workoutExercise = $workout->exercises()->create([
                'exercise_id' => $exercise->id,
                'order' => $exercise->pivot->order ?? 0,
            ]);

            $previousExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('ended_at'))
                ->where('exercise_id', $exercise->id)
                ->latest('id')
                ->with('sets')
                ->first();

            $targetSets = $exercise->pivot->target_sets ?? 3;

            for ($i = 1; $i <= $targetSets; $i++) {
                $previousSet = $previousExercise?->sets->firstWhere('set_number', $i);

                $workoutExercise->sets()->create([
                    'set_number' => $i,
                    'weight' => $this->sanitizeSetValue($previousSet?->weight ?? $exercise->pivot->target_weight),
                    'reps' => $this->sanitizeSetValue($previousSet?->reps ?? $exercise->pivot->target_reps),
                    'completed' => false,
                ]);
            }
        }
    }

    private function assertOwnsWorkout(User $user, Workout $workout): void
    {
        if ($workout->user_id !== $user->id) {
            throw new AuthorizationException('This workout does not belong to you.');
        }
    }

    private function assertOwnsWorkoutExercise(User $user, WorkoutExercise $workoutExercise): void
    {
        $this->assertOwnsWorkout($user, $workoutExercise->workout);
    }

    /**
     * workout_sets.weight/reps/rpe are numeric columns (numeric on postgres):
     * template values like "Banda" or "15-20" are valid for the routine card
     * but must not be persisted into a set, which would 500 on postgres.
     */
    private function sanitizeSetValue(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
