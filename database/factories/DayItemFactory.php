<?php

namespace Database\Factories;

use App\Models\Day;
use App\Models\DayItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DayItem>
 */
class DayItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_id' => Day::factory(),
            'task_id' => null,
            'title' => fake()->sentence(4),
            'anchor' => fake()->randomElement(DayItem::ANCHORS),
            'position' => fake()->numberBetween(1, 3),
            'state' => 'pending',
            'closing_note' => null,
            'done_at' => null,
        ];
    }
}
