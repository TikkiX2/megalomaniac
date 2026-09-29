<?php

declare(strict_types=1);

namespace App\Models;

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Person extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'nickname', 'birthday',
        'email', 'phone', 'whatsapp', 'address', 'city', 'country',
        'company', 'job_title', 'website', 'how_we_met', 'closeness',
        'relationship_status', 'preferred_contact_channel', 'is_favorite',
        'is_archived', 'last_contacted_at', 'notes',
    ];

    protected $appends = ['avatar_url', 'full_name'];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'closeness' => Closeness::class,
            'relationship_status' => RelationshipStatus::class,
            'preferred_contact_channel' => PreferredContactChannel::class,
            'is_favorite' => 'boolean',
            'is_archived' => 'boolean',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function refreshLastContactedAt(): void
    {
        $this->forceFill([
            'last_contacted_at' => $this->interactions()->max('occurred_at'),
        ])->saveQuietly();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PersonInteraction::class);
    }

    public function keyDates(): HasMany
    {
        return $this->hasMany(PersonKeyDate::class)->orderBy('date');
    }

    public function socials(): HasMany
    {
        return $this->hasMany(PersonSocial::class)->orderBy('network');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
