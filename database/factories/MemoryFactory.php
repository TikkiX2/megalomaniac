<?php

namespace Database\Factories;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'scope' => MemoryScope::Global,
            'thread_id' => null,
            'content' => fake()->sentence(6),
            'source' => 'user',
            'content_hash' => fn (array $attributes): string => MemoryCatalog::hashContent($attributes['content']),
            'metadata' => null,
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['scope' => MemoryScope::Global, 'thread_id' => null]);
    }

    public function forThread(ChatThread $thread): static
    {
        return $this->state(fn () => [
            'scope' => MemoryScope::Thread,
            'thread_id' => $thread->getKey(),
        ]);
    }
}
