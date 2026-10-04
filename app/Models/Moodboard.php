<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $project_id
 * @property string $name
 */
class Moodboard extends Model
{
    /**
     * Moodboards only keep a creation timestamp (spec §Modelo de datos).
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'project_id',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<Moodboard>  $query
     * @return Builder<Moodboard>
     */
    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        return $query->where('user_id', $user instanceof User ? $user->id : $user);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function savedImages(): HasMany
    {
        return $this->hasMany(SavedImage::class);
    }

    public function isInbox(): bool
    {
        return $this->project_id === null;
    }
}
