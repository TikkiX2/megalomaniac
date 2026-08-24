<?php

namespace Database\Factories;

use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    protected $model = WorkoutSet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_exercise_id' => WorkoutExercise::factory(),
            'set_number' => 1,
            'weight' => $this->faker->randomFloat(2, 20, 100),
            'reps' => $this->faker->numberBetween(5, 15),
            'rpe' => $this->faker->numberBetween(6, 10),
            'completed' => false,
        ];
    }
}
