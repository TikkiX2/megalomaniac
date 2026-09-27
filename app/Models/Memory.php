<?php

namespace App\Models;

use App\Ai\Enums\MemoryScope;
use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Memory $memory): void {
            if (blank($memory->id)) {
                $memory->id = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => MemoryScope::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function scopeGlobal(Builder $query): void
    {
        $query->where('scope', MemoryScope::Global->value)->whereNull('thread_id');
    }

    public function scopeForThread(Builder $query, ChatThread $thread): void
    {
        $query->where('scope', MemoryScope::Thread->value)->where('thread_id', $thread->getKey());
    }
}
