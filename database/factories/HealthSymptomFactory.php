<?php

namespace Database\Factories;

use App\Health\Enums\Severity;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthSymptom>
 */
class HealthSymptomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'symptom' => 'calambre',
            'severity' => Severity::Mild,
            'occurred_at' => now(),
            'notes' => null,
        ];
    }
}
