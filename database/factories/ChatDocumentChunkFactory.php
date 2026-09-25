<?php

namespace Database\Factories;

use App\Models\ChatAttachment;
use App\Models\ChatDocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatDocumentChunk>
 */
class ChatDocumentChunkFactory extends Factory
{
    protected $model = ChatDocumentChunk::class;

    public function definition(): array
    {
        return [
            'attachment_id' => ChatAttachment::factory(),
            'position' => 0,
            'content' => fake()->paragraph(),
        ];
    }
}
