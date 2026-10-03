<?php

namespace Database\Factories;

use App\Health\Enums\StudyType;
use App\Models\HealthStudy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthStudy>
 */
class HealthStudyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'type' => StudyType::Lab,
            'title' => $this->faker->sentence(),
            'performed_at' => $this->faker->date(),
            'provider_id' => null,
            'condition_id' => null,
            'notes' => null,
        ];
    }
}
