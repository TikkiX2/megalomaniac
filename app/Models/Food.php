<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $table = 'foods';

    protected $fillable = [
        'name',
        'brand',
        'calories',
        'protein',
        'carbs',
        'fats',
        'serving_size',
        'serving_unit',
        'image_url',
    ];

    protected $casts = [
        'protein' => 'decimal:2',
        'carbs' => 'decimal:2',
        'fats' => 'decimal:2',
        'serving_size' => 'decimal:2',
    ];
}
