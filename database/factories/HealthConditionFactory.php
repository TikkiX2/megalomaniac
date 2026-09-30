<?php

namespace Database\Factories;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Models\HealthCondition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthCondition>
 */
class HealthConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'kind' => ConditionKind::Condition,
            'name' => $this->faker->randomElement(['Hipertensión', 'Asma', 'Migraña', 'Anemia']),
            'status' => ConditionStatus::Active,
            'severity' => null,
            'diagnosed_at' => $this->faker->optional()->dateTimeBetween('-10 years', 'now'),
            'provider_id' => null,
            'notes' => null,
        ];
    }
}
