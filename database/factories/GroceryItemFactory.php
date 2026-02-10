<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroceryItem>
 */
class GroceryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->word(),
            'category' => $this->faker->randomElement(['Dairy', 'Meat', 'Vegetables', 'Fruit', 'Bakery', 'Other']),
            'quantity' => $this->faker->randomFloat(2, 1, 10),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'g', 'l', 'ml', 'packs']),
            'price' => $this->faker->randomFloat(2, 1, 50),
            'is_purchased' => false,
            'purchased_at' => null,
        ];
    }
}
