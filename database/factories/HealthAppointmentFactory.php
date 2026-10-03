<?php

namespace Database\Factories;

use App\Health\Enums\AppointmentStatus;
use App\Models\HealthAppointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthAppointment>
 */
class HealthAppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'provider_id' => null,
            'title' => $this->faker->sentence(3),
            'scheduled_at' => $this->faker->dateTimeBetween('+1 days', '+1 month'),
            'status' => AppointmentStatus::Scheduled,
            'notes' => null,
        ];
    }
}
