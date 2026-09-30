<?php

namespace Database\Factories;

use App\Health\Enums\MeasurementType;
use App\Models\HealthMeasurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthMeasurement>
 */
class HealthMeasurementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'type' => MeasurementType::Weight,
            'value' => 70.5,
            'secondary_value' => null,
            'unit' => 'kg',
            'measured_at' => now(),
            'notes' => null,
        ];
    }
}
