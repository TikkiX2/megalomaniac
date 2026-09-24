<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Ai\Models\ConversationMessage;

class ChatMessage extends ConversationMessage
{
    use HasFactory;

    public $incrementing = false;

    /**
     * @return array<int, array{url: string, title: ?string, start_index: ?int, end_index: ?int}>
     */
    public function citations(): array
    {
        return $this->meta['citations'] ?? [];
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}
