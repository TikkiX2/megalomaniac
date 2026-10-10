<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bloque extends Model
{
    use HasFactory;

    protected $table = 'bloques';

    protected $fillable = [
        'user_id',
        'etiqueta',
        'dia_semana',
        'hora_inicio',
        'duracion_min',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'duracion_min' => 'integer',
            'activo' => 'boolean',
            'hora_inicio' => 'string',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
