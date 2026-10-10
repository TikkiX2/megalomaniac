<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColaMediaItem extends Model
{
    use HasFactory;

    public const TIPOS = ['serie', 'pelicula', 'libro', 'juego'];

    protected $table = 'cola_media';

    protected $fillable = [
        'user_id',
        'titulo',
        'tipo',
        'posicion',
    ];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
