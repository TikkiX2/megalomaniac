<?php

namespace Database\Factories;

use App\Ai\Enums\AiProtocol;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->word(),
            'protocol' => AiProtocol::OpenAiCompatible->value,
            'url' => 'https://api.example.com/v1',
            'key' => 'sk-test',
            'model' => 'test-model',
            'embeddings_model' => null,
            'enabled' => true,
            'sort_order' => 0,
        ];
    }
}
