<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectPayment>
 */
class ProjectPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'currency_id' => Currency::factory(),
            'payment_date' => $this->faker->date(),
            'status' => $this->faker->randomElement(['pending', 'received', 'cancelled']),
            'method' => $this->faker->randomElement(['bank_transfer', 'paypal', 'stripe', 'cash']),
            'transaction_id' => $this->faker->uuid(),
            'notes' => $this->faker->sentence(),
        ];
    }
}
