<?php

namespace App\Services\Gym;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class RoutineService
{
    public function __construct(protected ExerciseResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Routine
    {
        $routine = $user->routines()->create([
            'name' => $data['name'],
            'focus' => $data['focus'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['exercises'])) {
            $this->attachExercises($routine, $data['exercises']);
        }

        return $routine;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Routine $routine, array $data): Routine
    {
        $this->assertOwns($user, $routine);

        $routine->update(array_filter(
            Arr::only($data, ['name', 'focus', 'scheduled_date', 'status']),
            fn ($value) => $value !== null,
        ));

        if (array_key_exists('exercises', $data) && $data['exercises'] !== null) {
            $routine->exercises()->detach();
            $this->attachExercises($routine, $data['exercises']);
        }

        return $routine->refresh();
    }

    public function delete(User $user, Routine $routine): void
    {
        $this->assertOwns($user, $routine);

        $routine->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $exercises
     */
    private function attachExercises(Routine $routine, array $exercises): void
    {
        foreach (array_values($exercises) as $index => $exerciseData) {
            $exercise = $this->resolver->resolve(
                $exerciseData['id'] ?? null,
                $exerciseData['name'] ?? null,
                $exerciseData,
            );

            $routine->exercises()->attach($exercise->id, [
                'order' => $index + 1,
                'target_sets' => $exerciseData['target_sets'] ?? null,
                'target_reps' => $exerciseData['target_reps'] ?? null,
                'target_weight' => $exerciseData['target_weight'] ?? null,
                'notes' => $exerciseData['notes'] ?? null,
            ]);
        }
    }

    private function assertOwns(User $user, Routine $routine): void
    {
        if ($routine->user_id !== $user->id) {
            throw new AuthorizationException('This routine does not belong to you.');
        }
    }
}
