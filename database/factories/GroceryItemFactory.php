<?php

namespace Database\Factories;

use App\Models\GroceryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroceryItem>
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
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'category' => $this->faker->randomElement(['Dairy', 'Meat', 'Vegetables', 'Fruit', 'Bakery', 'Other']),
            'current_stock' => $this->faker->randomFloat(2, 1, 10),
            'target_stock' => $this->faker->randomFloat(2, 5, 20),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'g', 'l', 'ml', 'packs']),
            'price' => $this->faker->randomFloat(2, 1, 50),
            'purchased_at' => null,
        ];
    }
}
