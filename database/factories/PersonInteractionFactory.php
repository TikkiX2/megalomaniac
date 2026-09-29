<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\InteractionChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonInteraction>
 */
class PersonInteractionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => Person::factory(),
            'channel' => $this->faker->randomElement(InteractionChannel::cases()),
            'occurred_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'title' => $this->faker->optional()->sentence(3),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
