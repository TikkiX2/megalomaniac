<?php

namespace Database\Factories;

use App\Models\HealthGymCrossover;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HealthGymCrossoverFactory extends Factory
{
    protected $model = HealthGymCrossover::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symptom_id' => $this->faker->numberBetween(1, 100),
            'workout_set_id' => $this->faker->numberBetween(1, 100),
            'occurred_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'notes' => $this->faker->sentence,
        ];
    }
}
