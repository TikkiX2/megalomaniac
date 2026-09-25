<?php

namespace Database\Factories;

use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Enums\TransportKind;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'github',
            'name' => fake()->unique()->words(2, true),
            'auth_type' => AuthType::ApiToken,
            'credentials' => ['token' => 'ghp_'.fake()->sha1()],
            'base_url' => 'https://api.github.com',
            'transport' => TransportKind::Direct,
            'transport_config' => null,
            'options' => null,
            'enabled' => true,
            'status' => ConnectionStatus::Unknown,
            'status_message' => null,
            'last_tested_at' => null,
            'last_used_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }
}
