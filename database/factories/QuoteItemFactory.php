<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hours = $this->faker->randomFloat(1, 1, 40);
        $rate = $this->faker->randomFloat(2, 30, 100);

        return [
            'quote_id' => \App\Models\Quote::factory(),
            'description' => $this->faker->sentence(),
            'hours' => $hours,
            'hourly_rate' => $rate,
            'subtotal' => $hours * $rate,
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
