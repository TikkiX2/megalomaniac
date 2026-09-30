<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'name', 'dose_amount', 'dose_unit', 'route',
        'frequency_text', 'started_at', 'ended_at', 'is_active',
        'condition_id', 'prescriber_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dose_amount' => 'decimal:2',
            'started_at' => 'date',
            'ended_at' => 'date',
            'is_active' => 'boolean',
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

    public function condition(): BelongsTo
    {
        return $this->belongsTo(HealthCondition::class, 'condition_id');
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(HealthProfessional::class, 'prescriber_id');
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(HealthMedicationIntake::class, 'medication_id');
    }
}
