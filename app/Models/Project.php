<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'name',
        'description',
        'status',
        'start_date',
        'end_date',
        'deadline',
        'total_amount',
        'currency_id',
        'paid_amount',
        'hourly_rate',
        'estimated_hours',
        'area',
        'module',
        'priority',
        'urgency',
        'importance',
        'tags',
        'notes',
        'is_archived',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'deadline' => 'date',
        'description' => 'array',
        'tags' => 'array',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'is_archived' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress', 'maintenance']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Currency::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProjectPayment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function updatePaidAmount()
    {
        $this->paid_amount = $this->payments()->where('status', 'received')->sum('amount');
        $this->save();
    }
}
