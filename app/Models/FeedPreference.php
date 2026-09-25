<?php

namespace App\Models;

use Database\Factories\FeedPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedPreference extends Model
{
    /** @use HasFactory<FeedPreferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'embedding', 'topic_weights', 'source_weights',
        'likes', 'dislikes', 'saves', 'opens',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'topic_weights' => 'array',
            'source_weights' => 'array',
            'likes' => 'integer',
            'dislikes' => 'integer',
            'saves' => 'integer',
            'opens' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            [
                'topic_weights' => [],
                'source_weights' => [],
            ],
        );
    }
}
