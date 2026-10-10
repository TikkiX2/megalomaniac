<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Day extends Model
{
    use HasFactory;

    protected $table = 'days';

    protected $fillable = [
        'user_id',
        'date',
        'pick_type',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DayItem::class, 'day_id')->orderBy('position');
    }

    public function visibleItems(): HasMany
    {
        return $this->hasMany(DayItem::class, 'day_id')
            ->where('state', '!=', 'released')
            ->orderBy('position');
    }
}
