<?php

namespace App\Services\Gym;

use App\Models\Exercise;
use Illuminate\Support\Str;
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
        $key = $this->normalize($name);

        return Exercise::all()
            ->first(fn (Exercise $exercise) => $this->normalize($exercise->name) === $key)
            ?? Exercise::create([
                'name' => $name,
                'muscle_group' => $attributes['muscle_group'] ?? null,
                'type' => $attributes['type'] ?? null,
            ]);
    }

    private function normalize(string $name): string
    {
        return mb_strtolower(Str::ascii(trim($name)));
    }
}
