<?php

namespace App\Services\Gym;

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Support\Collection;

class PersonalRecordService
{
    private const EPSILON = 0.001;

    public function evaluate(User $user, WorkoutSet $set): void
    {
        if (! $set->completed || $set->weight === null || $set->reps === null) {
            return;
        }

        $workoutExercise = WorkoutExercise::with('exercise')->find($set->workout_exercise_id);

        if (! $workoutExercise?->exercise) {
            return;
        }

        $exercise = $workoutExercise->exercise;
        $weight = (float) $set->weight;
        $reps = (int) $set->reps;

        $this->beat($user, $exercise, $set, 'weight', $weight, $weight, $reps);
        $this->beat($user, $exercise, $set, 'one_rm', round($weight * (1 + $reps / 30), 2), $weight, $reps);

        $repsBest = WorkoutSet::query()
            ->whereHas('workoutExercise', function ($query) use ($user, $exercise) {
                $query->where('exercise_id', $exercise->id)
                    ->whereHas('workout', fn ($workoutQuery) => $workoutQuery->where('user_id', $user->id));
            })
            ->where('weight', $weight)
            ->where('completed', true)
            ->where('id', '!=', $set->id)
            ->max('reps');

        // A rep PR only exists when there is a previous completed set to beat at this weight.
        if ($repsBest !== null && $reps > (int) $repsBest) {
            $this->store($user, $exercise, $set, 'reps', $reps, $weight, $reps);
        }
    }

    /**
     * @return Collection<int, PersonalRecord>
     */
    public function timeline(User $user, int $limit = 50): Collection
    {
        return PersonalRecord::with('exercise')
            ->where('user_id', $user->id)
            ->latest('achieved_at')
            ->limit($limit)
            ->get();
    }

    public function bestWeightFor(User $user, Exercise $exercise): ?float
    {
        $best = PersonalRecord::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('type', 'weight')
            ->max('value');

        return $best === null ? null : (float) $best;
    }

    /**
     * @param  Collection<int, WorkoutSet>  $sets
     */
    public function annotateSets(Collection $sets): void
    {
        $prSetIds = PersonalRecord::query()
            ->whereIn('workout_set_id', $sets->pluck('id'))
            ->pluck('workout_set_id')
            ->all();

        $sets->each(fn (WorkoutSet $set) => $set->setAttribute(
            'is_pr',
            in_array($set->id, $prSetIds, true),
        ));
    }

    private function beat(User $user, Exercise $exercise, WorkoutSet $set, string $type, float $value, float $weight, int $reps): void
    {
        $currentBest = PersonalRecord::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('type', $type)
            ->max('value');

        if ($currentBest === null || $value > (float) $currentBest + self::EPSILON) {
            $this->store($user, $exercise, $set, $type, $value, $weight, $reps);
        }
    }

    private function store(User $user, Exercise $exercise, WorkoutSet $set, string $type, float $value, float $weight, int $reps): void
    {
        PersonalRecord::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_set_id' => $set->id,
            'type' => $type,
            'value' => $value,
            'reps' => $reps,
            'weight' => $weight,
            'achieved_at' => $set->updated_at ?? now(),
        ]);
    }
}
