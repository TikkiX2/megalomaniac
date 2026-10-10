<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dia extends Model
{
    use HasFactory;

    protected $table = 'dias';

    protected $fillable = [
        'user_id',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DiaItem::class, 'dia_id')->orderBy('posicion');
    }

    public function itemsVisibles(): HasMany
    {
        return $this->hasMany(DiaItem::class, 'dia_id')
            ->where('estado', '!=', 'soltado')
            ->orderBy('posicion');
    }
}
