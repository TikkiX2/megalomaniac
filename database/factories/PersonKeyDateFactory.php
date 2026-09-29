<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Models\User;
use App\People\Enums\KeyDateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonKeyDate>
 */
class PersonKeyDateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => Person::factory(),
            'type' => KeyDateType::Custom,
            'label' => $this->faker->optional()->word(),
            'date' => $this->faker->dateTimeBetween('-5 years', '+1 year'),
            'remind_days_before' => 7,
            'is_recurring_annually' => false,
        ];
    }
}
