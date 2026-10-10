<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class DiaItem extends Model
{
    use HasFactory;

    public const ANCLAS = [
        'al levantarme',
        'después de comer',
        'después del gimnasio',
        'después de bañarme',
        'antes de dormir',
        'sin ancla',
    ];

    public const ESTADOS = ['pendiente', 'hecho', 'soltado'];

    protected $table = 'dia_items';

    protected $fillable = [
        'dia_id',
        'tarea_id',
        'titulo',
        'ancla',
        'posicion',
        'estado',
        'nota_cierre',
        'hecho_at',
    ];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'hecho_at' => 'datetime',
        ];
    }

    public function dia(): BelongsTo
    {
        return $this->belongsTo(Dia::class, 'dia_id');
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'tarea_id');
    }

    protected static function booted(): void
    {
        static::saving(function (DiaItem $item) {
            if ($item->posicion < 1 || $item->posicion > 3) {
                throw ValidationException::withMessages([
                    'posicion' => 'La posición debe estar entre 1 y 3.',
                ]);
            }

            if (! in_array($item->ancla, self::ANCLAS, true)) {
                throw ValidationException::withMessages([
                    'ancla' => 'Ancla inválida.',
                ]);
            }

            if (! in_array($item->estado, self::ESTADOS, true)) {
                throw ValidationException::withMessages([
                    'estado' => 'Estado inválido.',
                ]);
            }

            // Máx 3 visibles (soltado libera slot): cuenta solo pendiente+hecho.
            $query = static::where('dia_id', $item->dia_id)
                ->whereIn('estado', ['pendiente', 'hecho']);
            if ($item->exists) {
                $query->where('id', '!=', $item->id);
            }
            // Si el item a guardar es soltado, no ocupa slot.
            if (in_array($item->estado, ['pendiente', 'hecho'], true) && $query->count() >= 3) {
                throw ValidationException::withMessages([
                    'dia_id' => 'Máximo 3 ítems por día.',
                ]);
            }
        });
    }
}
