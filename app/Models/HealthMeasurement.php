<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\MeasurementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'type', 'value', 'secondary_value',
        'unit', 'measured_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeasurementType::class,
            'value' => 'decimal:2',
            'secondary_value' => 'decimal:2',
            'measured_at' => 'datetime',
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
}
