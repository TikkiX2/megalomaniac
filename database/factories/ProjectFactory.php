<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $deadline = $this->faker->dateTimeBetween('now', '+1 year');
        $totalAmount = $this->faker->randomFloat(2, 500, 10000);

        return [
            'user_id' => \App\Models\User::factory(),
            'client_id' => \App\Models\Client::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->getYooptaContent($this->faker->paragraph()),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed', 'maintenance', 'cancelled']),
            'start_date' => $startDate,
            'end_date' => $this->faker->optional(0.3)->dateTimeBetween($startDate, $deadline),
            'deadline' => $deadline,
            'total_amount' => $totalAmount,
            'currency_id' => \App\Models\Currency::factory(),
            'paid_amount' => 0, // Should be updated via payments
            'hourly_rate' => $this->faker->randomFloat(2, 20, 150),
            'estimated_hours' => $totalAmount / 50, // rough estimate
            'area' => $this->faker->word(),
            'module' => $this->faker->word(),
            'priority' => $this->faker->randomElement(['Low', 'Normal', 'High', 'Urgent']),
            'urgency' => $this->faker->randomElement(['Low', 'Normal', 'High']),
            'importance' => $this->faker->randomElement(['Low', 'Normal', 'High']),
            'tags' => $this->faker->words(3),
            'notes' => $this->faker->paragraph(),
            'is_archived' => false,
        ];
    }

    private function getYooptaContent($text)
    {
        // Minimal Yoopta structure
        return [
            'id' => $this->faker->uuid(),
            'value' => [
                [
                    'id' => $this->faker->uuid(),
                    'type' => 'paragraph',
                    'children' => [['text' => $text]],
                ],
            ],
        ];
    }
}
