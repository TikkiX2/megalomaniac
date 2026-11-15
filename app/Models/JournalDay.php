<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\JournalBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'date', 'body', 'mood', 'tags', 'is_private',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'body' => JournalBody::class,
            'tags' => 'array',
            'mood' => 'integer',
            'is_private' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JournalDay $day): void {
            if ($day->isDirty('is_private')) {
                $day->body = $day->body;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moments(): HasMany
    {
        return $this->hasMany(JournalMoment::class);
    }

    public function hasContent(): bool
    {
        return filled($this->body) || $this->mood !== null || $this->moments()->exists();
    }

    /**
     * @param  Builder<JournalDay>  $query
     * @return Builder<JournalDay>
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }
}
