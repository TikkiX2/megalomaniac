<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthSymptomCatalog extends Model
{
    use HasFactory;

    protected $table = 'health_symptom_catalog';

    protected $fillable = ['user_id', 'name', 'severity_default', 'notes'];

    protected function casts(): array
    {
        return [
            'severity_default' => Severity::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(HealthSymptomEpisode::class, 'catalog_id');
    }

    public function symptoms(): HasMany
    {
        return $this->hasMany(HealthSymptom::class, 'catalog_id');
    }
}
