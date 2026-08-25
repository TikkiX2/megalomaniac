<?php

namespace Database\Factories;

use App\Models\Exercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word().' Press',
            'muscle_group' => $this->faker->randomElement(['Chest', 'Back', 'Legs', 'Shoulders', 'Arms']),
            'type' => $this->faker->randomElement(['Compound', 'Isolation', 'Machine', 'Bodyweight']),
        ];
    }
}
