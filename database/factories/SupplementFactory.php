<?php

namespace Database\Factories;

use App\Models\Supplement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplement>
 */
class SupplementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word().' Supplement',
            'brand' => $this->faker->company(),
            'dosage_amount' => $this->faker->randomElement(['5g', '1 capsule', '30g', '500mg']),
            'frequency' => $this->faker->randomElement(['Daily', 'Pre-workout', 'Post-workout', 'Before sleep']),
            'stock_quantity' => $this->faker->numberBetween(10, 50),
            'low_stock_threshold' => 5,
        ];
    }
}
