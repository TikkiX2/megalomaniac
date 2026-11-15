<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\JournalBody;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class JournalMoment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id', 'journal_day_id', 'occurred_at', 'title', 'body',
        'tags', 'is_private',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'title' => JournalBody::class,
            'body' => JournalBody::class,
            'tags' => 'array',
            'is_private' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JournalMoment $moment): void {
            if ($moment->isDirty('is_private')) {
                $moment->title = $moment->title;
                $moment->body = $moment->body;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(JournalDay::class, 'journal_day_id');
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'journal_moment_person', 'moment_id', 'person_id')
            ->withTimestamps();
    }
}
