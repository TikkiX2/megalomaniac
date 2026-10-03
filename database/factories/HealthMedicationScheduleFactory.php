<?php

namespace Database\Factories;

use App\Models\HealthMedicationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthMedicationSchedule>
 */
class HealthMedicationScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'medication_id' => null,
            'time' => $this->faker->time('H:i:s'),
            'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
            'is_active' => true,
        ];
    }
}
