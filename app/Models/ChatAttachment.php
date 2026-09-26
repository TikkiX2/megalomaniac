<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatAttachment extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $guarded = [];

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    /**
     * Threads this document is attached to through the sources library (N:N).
     *
     * @return BelongsToMany<ChatThread, $this>
     */
    public function threads(): BelongsToMany
    {
        return $this->belongsToMany(ChatThread::class, 'chat_thread_sources', 'attachment_id', 'thread_id')
            ->withTimestamps();
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ChatDocumentChunk::class, 'attachment_id');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function scopeImages(Builder $query): void
    {
        $query->where('kind', 'image');
    }

    public function scopeDocuments(Builder $query): void
    {
        $query->where('kind', 'document');
    }

    public function scopeReady(Builder $query): void
    {
        $query->where('status', 'ready');
    }

    public function scopeIndexed(Builder $query): void
    {
        $query->where('status', 'indexed');
    }

    public function isIndexed(): bool
    {
        return $this->status === 'indexed';
    }
}
