<?php

namespace App\Models;

use Database\Factories\FeedItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedItem extends Model
{
    /** @use HasFactory<FeedItemFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'feed_source_id', 'external_id', 'title', 'url', 'author',
        'summary', 'content_hash', 'published_at', 'fetched_at', 'embedding',
        'score', 'scored_at', 'is_saved', 'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'score' => 'float',
            'is_saved' => 'boolean',
            'published_at' => 'datetime',
            'fetched_at' => 'datetime',
            'scored_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class, 'feed_source_id');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(FeedSignal::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function scopeSaved(Builder $query): Builder
    {
        return $query->where('is_saved', true);
    }
}
