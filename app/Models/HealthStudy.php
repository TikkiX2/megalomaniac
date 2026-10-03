<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\StudyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class HealthStudy extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id', 'person_id', 'type', 'title', 'performed_at',
        'provider_id', 'condition_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => StudyType::class,
            'performed_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(HealthProfessional::class, 'provider_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(HealthCondition::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(HealthStudyResult::class, 'study_id')->orderBy('sort_order');
    }
}
