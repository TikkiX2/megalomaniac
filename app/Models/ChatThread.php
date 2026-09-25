<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Throwable;

class ChatThread extends Conversation
{
    use HasFactory;

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
            'tools_policy' => 'array',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'thread_id');
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

    /**
     * Build an untrusted, formatted context block from the thread's indexed
     * documents that match the given query. Returns null when there is
     * nothing relevant (or the query has no usable terms).
     */
    public function documentContext(string $query, int $limit = 6): ?string
    {
        $terms = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->values();

        if ($terms->isEmpty() || $this->participant_id === null) {
            return null;
        }

        $match = $terms
            ->map(fn (string $term): string => $term.'*')
            ->implode(' OR ');

        try {
            $rows = DB::select(
                'select c.content, a.original_name
                 from chat_document_chunks_fts
                 join chat_document_chunks c on c.id = chat_document_chunks_fts.rowid
                 join chat_attachments a on a.id = c.attachment_id
                 where chat_document_chunks_fts match ?
                   and a.thread_id = ?
                   and a.user_id = ?
                   and a.status = ?
                 order by bm25(chat_document_chunks_fts)
                 limit ?',
                [$match, $this->id, $this->participant_id, 'indexed', $limit],
            );
        } catch (Throwable) {
            return null;
        }

        if ($rows === []) {
            return null;
        }

        return collect($rows)
            ->map(fn (object $row): string => '### '.$row->original_name."\n".$row->content)
            ->implode("\n\n");
    }
}
