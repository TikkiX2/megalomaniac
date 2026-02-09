<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MealLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'meal_type',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MealItem::class);
    }

    public function getTotalCaloriesAttribute()
    {
        return $this->items->sum('calories_snapshot');
    }

    public function getTotalMacrosAttribute()
    {
        return [
            'protein' => $this->items->sum('protein_snapshot'),
            'carbs' => $this->items->sum('carbs_snapshot'),
            'fats' => $this->items->sum('fats_snapshot'),
        ];
    }
}
