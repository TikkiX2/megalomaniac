<?php

namespace Database\Factories;

use App\Models\HealthAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HealthAlertFactory extends Factory
{
    protected $model = HealthAlert::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['symptom_severe', 'study_result_high']),
            'related_id' => $this->faker->numberBetween(1, 100),
            'related_type' => $this->faker->randomElement(['HealthSymptom', 'HealthStudyResult']),
            'message' => $this->faker->sentence,
            'is_read' => $this->faker->boolean,
        ];
    }
}
