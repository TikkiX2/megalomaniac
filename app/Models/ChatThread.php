<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Ai\Models\Conversation;

class ChatThread extends Conversation
{
    use HasFactory;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey());
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeWithMessages(Builder $query): void
    {
        $query->whereHas('messages');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('pinned_at')->orderByDesc('updated_at');
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where('title', 'like', '%'.addcslashes($term, '%_').'%');
    }

    public function belongsToUser(User $user): bool
    {
        return $this->participant_type === $user->getMorphClass()
            && (int) $this->participant_id === (int) $user->getKey();
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }
}
