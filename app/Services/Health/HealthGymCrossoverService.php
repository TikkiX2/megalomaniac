<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Models\HealthGymCrossover;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class HealthGymCrossoverService
{
    /**
     * Record a gym-crossover event linking a symptom to a workout set.
     */
    public function recordCrossover(User $user, array $data): HealthGymCrossover
    {
        return $user->healthGymCrossovers()->create($data);
    }

    /**
     * Get crossovers for a user, optionally filtered by symptom or workout.
     *
     * @return Collection
     */
    public function getCrossovers(User $user, ?int $symptomId = null, ?int $workoutSetId = null)
    {
        $query = $user->healthGymCrossovers();

        if ($symptomId !== null) {
            $query->where('symptom_id', $symptomId);
        }

        if ($workoutSetId !== null) {
            $query->where('workout_set_id', $workoutSetId);
        }

        return $query->orderByDesc('occurred_at')->get();
    }

    /**
     * Get statistics about crossovers for a user.
     */
    public function getStatistics(User $user): array
    {
        $crossovers = $user->healthGymCrossovers()->with(['symptom', 'workoutSet'])->get();

        // Group by symptom
        $bySymptom = $crossovers->groupBy(function ($crossover) {
            return $crossover->symptom->symptom ?? 'Unknown';
        })->map(function ($group) {
            return $group->count();
        });

        // Group by workout set
        $byWorkout = $crossovers->groupBy(function ($crossover) {
            return 'Set #'.$crossover->workout_set_id;
        })->map(function ($group) {
            return $group->count();
        });

        return [
            'total' => $crossovers->count(),
            'by_symptom' => $bySymptom->all(),
            'by_workout' => $byWorkout->all(),
        ];
    }
}
