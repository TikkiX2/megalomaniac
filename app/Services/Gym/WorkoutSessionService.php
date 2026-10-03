<?php

namespace App\Services\Gym;

use App\Exceptions\WorkoutAlreadyActiveException;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

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
                    'weight' => $previousSet?->weight ?? $exercise->pivot->target_weight,
                    'reps' => $previousSet?->reps ?? $exercise->pivot->target_reps,
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
}
