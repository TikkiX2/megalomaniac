<?php

namespace Database\Factories;

use App\Ai\Enums\AiScope;
use App\Models\User;
use App\Models\UserAiScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAiScope>
 */
class UserAiScopeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scope' => AiScope::Global->value,
            'provider_chain' => [],
            'prompt' => null,
        ];
    }
}
