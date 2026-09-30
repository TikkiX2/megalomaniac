<?php

namespace Database\Factories;

use App\Models\HealthMedication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthMedication>
 */
class HealthMedicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'name' => $this->faker->randomElement(['Levotiroxina', 'Ibuprofeno', 'Vitamina D']),
            'dose_amount' => 50,
            'dose_unit' => 'mcg',
            'route' => 'oral',
            'frequency_text' => null,
            'started_at' => null,
            'ended_at' => null,
            'is_active' => true,
            'condition_id' => null,
            'prescriber_id' => null,
            'notes' => null,
        ];
    }
}
