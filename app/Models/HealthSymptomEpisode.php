<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthSymptomEpisode extends Model
{
    use HasFactory;

    protected $table = 'health_symptom_episodes';

    protected $fillable = ['user_id', 'person_id', 'catalog_id', 'started_at', 'ended_at', 'severity', 'notes'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'severity' => Severity::class,
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

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(HealthSymptomCatalog::class, 'catalog_id');
    }

    public function symptoms(): HasMany
    {
        return $this->hasMany(HealthSymptom::class, 'episode_id');
    }
}
