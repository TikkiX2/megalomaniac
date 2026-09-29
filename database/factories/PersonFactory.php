<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use App\People\Enums\Closeness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'nickname' => null,
            'birthday' => $this->faker->optional()->dateTimeBetween('-60 years', '-18 years'),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'whatsapp' => null,
            'address' => null,
            'city' => $this->faker->optional()->city(),
            'country' => $this->faker->optional()->country(),
            'company' => $this->faker->optional()->company(),
            'job_title' => null,
            'website' => null,
            'how_we_met' => null,
            'closeness' => Closeness::Friend,
            'relationship_status' => null,
            'preferred_contact_channel' => null,
            'is_favorite' => false,
            'is_archived' => false,
            'last_contacted_at' => null,
            'notes' => null,
        ];
    }
}
