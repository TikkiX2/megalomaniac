<?php

namespace App\Models;

use Database\Factories\FeedSignalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedSignal extends Model
{
    /** @use HasFactory<FeedSignalFactory> */
    use HasFactory;

    public const LIKE = 'like';

    public const DISLIKE = 'dislike';

    public const SAVE = 'save';

    public const HIDE = 'hide';

    public const OPEN = 'open';

    protected $fillable = ['feed_item_id', 'user_id', 'type'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class, 'feed_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
