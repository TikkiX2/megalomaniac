<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMedicationSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'medication_id',
        'time',
        'days_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'time' => 'datetime:H:i',
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(HealthMedication::class, 'medication_id');
    }
}
