<?php

namespace App\Models;

use Database\Factories\FeedDigestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedDigest extends Model
{
    /** @use HasFactory<FeedDigestFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'content', 'item_ids', 'sent_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'item_ids' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
