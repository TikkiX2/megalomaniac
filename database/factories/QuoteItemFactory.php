<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
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
            'quote_id' => Quote::factory(),
            'description' => $this->faker->sentence(),
            'hours' => $hours,
            'hourly_rate' => $rate,
            'subtotal' => $hours * $rate,
            'order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
