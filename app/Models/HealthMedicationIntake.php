<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\IntakeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMedicationIntake extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'medication_id', 'taken_at', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => IntakeStatus::class,
            'taken_at' => 'datetime',
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
