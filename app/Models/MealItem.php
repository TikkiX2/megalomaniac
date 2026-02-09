<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_log_id',
        'food_id',
        'quantity',
        'calories_snapshot',
        'protein_snapshot',
        'carbs_snapshot',
        'fats_snapshot',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'protein_snapshot' => 'decimal:2',
        'carbs_snapshot' => 'decimal:2',
        'fats_snapshot' => 'decimal:2',
    ];

    public function mealLog(): BelongsTo
    {
        return $this->belongsTo(MealLog::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
