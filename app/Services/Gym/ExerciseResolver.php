<?php

namespace App\Services\Gym;

use App\Models\Exercise;
use InvalidArgumentException;

class ExerciseResolver
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolve(?int $exerciseId = null, ?string $exerciseName = null, array $attributes = []): Exercise
    {
        if ($exerciseId) {
            return Exercise::findOrFail($exerciseId);
        }

        if ($exerciseName === null || trim($exerciseName) === '') {
            throw new InvalidArgumentException('Either exercise_id or exercise_name is required.');
        }

        $name = trim($exerciseName);

        return Exercise::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Exercise::create([
                'name' => $name,
                'muscle_group' => $attributes['muscle_group'] ?? null,
                'type' => $attributes['type'] ?? null,
            ]);
    }
}
