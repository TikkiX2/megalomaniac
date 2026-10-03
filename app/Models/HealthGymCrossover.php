<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthGymCrossover extends Model
{
    use HasFactory;

    protected $table = 'health_gym_crossovers';

    protected $fillable = [
        'user_id',
        'symptom_id',
        'workout_set_id',
        'occurred_at',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function symptom(): BelongsTo
    {
        return $this->belongsTo(HealthSymptom::class, 'symptom_id');
    }

    public function workoutSet(): BelongsTo
    {
        return $this->belongsTo(WorkoutSet::class, 'workout_set_id');
    }
}
