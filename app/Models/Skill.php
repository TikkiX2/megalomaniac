<?php

namespace App\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Skill $skill): void {
            if (blank($skill->id)) {
                $skill->id = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }
}
