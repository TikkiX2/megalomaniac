<?php

namespace Database\Factories;

use App\Health\Enums\IntakeStatus;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthMedicationIntake>
 */
class HealthMedicationIntakeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'medication_id' => HealthMedication::factory(),
            'taken_at' => now()->subDay(),
            'status' => IntakeStatus::Taken,
            'notes' => null,
        ];
    }
}
