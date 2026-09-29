<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'workout_set_id',
        'type',
        'value',
        'reps',
        'weight',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'weight' => 'decimal:2',
            'reps' => 'integer',
            'achieved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutSet(): BelongsTo
    {
        return $this->belongsTo(WorkoutSet::class);
    }
}
