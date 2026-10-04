<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $moodboard_id
 * @property string $source
 * @property string $source_id
 * @property string|null $thumb_path
 * @property string|null $full_path
 * @property array<int, string>|null $tags
 * @property string $download_status
 */
class SavedImage extends Model
{
    /**
     * Saved images only keep a creation timestamp (spec §Modelo de datos).
     */
    public const UPDATED_AT = null;

    /** Only the local thumbnail is stored (default). */
    public const STATUS_THUMB = 'thumb';

    /** The full-size image has been downloaded. */
    public const STATUS_FULL = 'full';

    /** The last requested download (thumbnail or full) failed. */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'moodboard_id',
        'source',
        'source_id',
        'title',
        'author',
        'author_url',
        'page_url',
        'image_url',
        'thumb_path',
        'full_path',
        'width',
        'height',
        'tags',
        'license',
        'maturity',
        'note',
        'download_status',
        'downloaded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<SavedImage>  $query
     * @return Builder<SavedImage>
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function moodboard(): BelongsTo
    {
        return $this->belongsTo(Moodboard::class);
    }
}
