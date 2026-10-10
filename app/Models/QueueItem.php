<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueItem extends Model
{
    use HasFactory;

    public const TYPES = ['pelicula', 'serie', 'disco', 'libro', 'juego'];

    /** @var array<string, string> Labels en español por tipo (copy visible). */
    public const TYPE_LABELS = [
        'pelicula' => 'Película',
        'serie' => 'Serie',
        'disco' => 'Disco',
        'libro' => 'Libro',
        'juego' => 'Juego',
    ];

    protected $table = 'queue_items';

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'position',
        'source',
        'external_id',
        'cover_url',
        'year',
        'creator',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
