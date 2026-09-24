<?php

namespace Database\Factories;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'participant_type' => (new User)->getMorphClass(),
            'participant_id' => User::factory(),
            'title' => fake()->sentence(4),
            'agent' => 'megalomaniac',
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['pinned_at' => now()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
