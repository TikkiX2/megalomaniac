<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'conversation_id' => ChatThread::factory(),
            'participant_type' => (new User)->getMorphClass(),
            'participant_id' => User::factory(),
            'agent' => 'megalomaniac',
            'role' => 'user',
            'content' => fake()->paragraph(),
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => 'assistant']);
    }

    public function withCitations(array $citations): static
    {
        return $this->state(fn () => ['meta' => ['citations' => $citations]]);
    }
}
