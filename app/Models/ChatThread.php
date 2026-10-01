<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Models\Conversation;
use Throwable;

class ChatThread extends Conversation
{
    use HasFactory;

    public const CATEGORY_GENERAL = 'general';

    public const CATEGORY_HEALTH = 'salud';

    /**
     * Module key of the health assistant. Health threads were born before the
     * `module` column and are still identified by `category = 'salud'`, so this
     * constant is only the key they resolve to as a module.
     */
    public const MODULE_HEALTH = 'health';

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

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'thread_id');
    }

    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Library documents explicitly attached to this thread (N:N).
     *
     * @return BelongsToMany<ChatAttachment, $this>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(ChatAttachment::class, 'chat_thread_sources', 'thread_id', 'attachment_id')
            ->withTimestamps();
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

    public function scopeCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
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

        try {
            $rows = DB::connection()->getDriverName() === 'pgsql'
                ? $this->searchDocumentsOnPostgres($terms, $limit)
                : $this->searchDocumentsOnSqlite($terms, $limit);
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

    /**
     * @param  Collection<int, string>  $terms
     * @return array<int, object>
     */
    private function searchDocumentsOnSqlite(Collection $terms, int $limit): array
    {
        $match = $terms
            ->map(fn (string $term): string => $term.'*')
            ->implode(' OR ');

        return DB::select(
            'select c.content, a.original_name
             from chat_document_chunks_fts
             join chat_document_chunks c on c.id = chat_document_chunks_fts.rowid
             join chat_attachments a on a.id = c.attachment_id
             join chat_thread_sources s on s.attachment_id = a.id
             where chat_document_chunks_fts match ?
               and s.thread_id = ?
               and a.user_id = ?
               and a.status = ?
             order by bm25(chat_document_chunks_fts)
             limit ?',
            [$match, $this->id, $this->participant_id, 'indexed', $limit],
        );
    }

    /**
     * @param  Collection<int, string>  $terms
     * @return array<int, object>
     */
    private function searchDocumentsOnPostgres(Collection $terms, int $limit): array
    {
        $match = $terms
            ->map(fn (string $term): string => $term.':*')
            ->implode(' | ');

        return DB::select(
            "select c.content, a.original_name
             from chat_document_chunks c
             join chat_attachments a on a.id = c.attachment_id
             join chat_thread_sources s on s.attachment_id = a.id
             where to_tsvector('simple', c.content) @@ to_tsquery('simple', ?)
               and s.thread_id = ?
               and a.user_id = ?
               and a.status = ?
             order by ts_rank(to_tsvector('simple', c.content), to_tsquery('simple', ?)) desc
             limit ?",
            [$match, $this->id, $this->participant_id, 'indexed', $match, $limit],
        );
    }
}
