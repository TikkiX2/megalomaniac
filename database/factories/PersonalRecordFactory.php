<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalRecord>
 */
class PersonalRecordFactory extends Factory
{
    protected $model = PersonalRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_id' => Exercise::factory(),
            'workout_set_id' => null,
            'type' => 'weight',
            'value' => $this->faker->randomFloat(2, 20, 120),
            'reps' => $this->faker->numberBetween(1, 12),
            'weight' => $this->faker->randomFloat(2, 20, 120),
            'achieved_at' => now(),
        ];
    }
}
