<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_number' => 'Q-'.$this->faker->unique()->numerify('####'),
            'user_id' => \App\Models\User::factory(),
            'client_id' => \App\Models\Client::factory(),
            'project_id' => null,
            'title' => $this->faker->sentence(4),
            'status' => $this->faker->randomElement(['draft', 'sent', 'accepted', 'rejected', 'expired']),
            'issue_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'valid_until' => $this->faker->dateTimeBetween('now', '+1 month'),
            'currency_id' => \App\Models\Currency::factory(),
            'subtotal' => 0, // Calculated from items
            'tax_amount' => 0,
            'total' => 0,
            'notes' => $this->faker->paragraph(),
            'terms_and_conditions' => $this->faker->paragraph(),
        ];
    }

    public function configure()
    {
        return $this->afterCreating(function (\App\Models\Quote $quote) {
            $items = \App\Models\QuoteItem::factory()->count(3)->create([
                'quote_id' => $quote->id,
            ]);

            $subtotal = $items->sum('subtotal');
            $quote->update([
                'subtotal' => $subtotal,
                'total' => $subtotal, // Assuming no tax for now
            ]);
        });
    }
}
