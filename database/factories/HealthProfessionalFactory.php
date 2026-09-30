<?php

namespace Database\Factories;

use App\Health\Enums\ProfessionalType;
use App\Models\HealthProfessional;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthProfessional>
 */
class HealthProfessionalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => ProfessionalType::Professional,
            'name' => $this->faker->name(),
            'specialty' => $this->faker->randomElement(['Neurología', 'Endocrinología', 'Clínica médica', 'Kinesiología']),
            'phone' => null,
            'email' => null,
            'address' => null,
            'notes' => null,
            'is_active' => true,
        ];
    }
}
