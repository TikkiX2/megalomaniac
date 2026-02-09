<?php

namespace Database\Factories;

use App\Models\Supplement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupplementLog>
 */
class SupplementLogFactory extends Factory
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
            'supplement_id' => Supplement::factory(),
            'taken_at' => now(),
        ];
    }
}
