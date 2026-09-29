<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonSocial>
 */
class PersonSocialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'network' => $this->faker->randomElement(['instagram', 'x', 'linkedin', 'github', 'telegram']),
            'handle' => '@'.$this->faker->userName(),
            'url' => $this->faker->optional()->url(),
        ];
    }
}
