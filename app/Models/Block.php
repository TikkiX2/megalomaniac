<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasFactory;

    protected $table = 'blocks';

    protected $fillable = [
        'user_id',
        'label',
        'weekday',
        'start_time',
        'duration_min',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'duration_min' => 'integer',
            'active' => 'boolean',
            'start_time' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
