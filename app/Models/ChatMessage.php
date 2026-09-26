<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Ai\Models\ConversationMessage;

class ChatMessage extends ConversationMessage
{
    use HasFactory;

    public $incrementing = false;

    /**
     * Attachments linked to this message.
     *
     * Note: `attachments` is also a cast column on the parent model (the SDK
     * stores the prompt attachments there), so the relation must be accessed
     * via `attachments()` / `getRelationValue('attachments')`, never as a
     * magic property.
     *
     * @return HasMany<ChatAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'message_id');
    }

    /**
     * @return array<int, array{url: string, title: ?string, snippet?: ?string, start_index?: ?int, end_index?: ?int}>
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
