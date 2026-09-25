<?php

namespace Database\Factories;

use App\Models\ChatAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatAttachment>
 */
class ChatAttachmentFactory extends Factory
{
    protected $model = ChatAttachment::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'kind' => 'image',
            'disk' => 'local',
            'path' => 'ai-attachments/qa/test.png',
            'original_name' => 'test.png',
            'mime' => 'image/png',
            'size' => 1234,
            'status' => 'ready',
        ];
    }
}
