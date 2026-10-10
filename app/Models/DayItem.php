<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class DayItem extends Model
{
    use HasFactory;

    public const ANCHORS = [
        'wake_up',
        'after_meal',
        'after_gym',
        'after_shower',
        'before_sleep',
        'no_anchor',
    ];

    public const STATES = ['pending', 'done', 'released'];

    protected $table = 'day_items';

    protected $fillable = [
        'day_id',
        'task_id',
        'title',
        'anchor',
        'position',
        'state',
        'closing_note',
        'done_at',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(Day::class, 'day_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    protected static function booted(): void
    {
        static::saving(function (DayItem $item) {
            if ($item->position < 1 || $item->position > 3) {
                throw ValidationException::withMessages([
                    'position' => 'The position must be between 1 and 3.',
                ]);
            }

            if (! in_array($item->anchor, self::ANCHORS, true)) {
                throw ValidationException::withMessages([
                    'anchor' => 'Invalid anchor.',
                ]);
            }

            if (! in_array($item->state, self::STATES, true)) {
                throw ValidationException::withMessages([
                    'state' => 'Invalid state.',
                ]);
            }

            // Máx 3 visibles (released libera slot): cuenta solo pending+done.
            $query = static::where('day_id', $item->day_id)
                ->whereIn('state', ['pending', 'done']);
            if ($item->exists) {
                $query->where('id', '!=', $item->id);
            }
            // Si el item a guardar es released, no ocupa slot.
            if (in_array($item->state, ['pending', 'done'], true) && $query->count() >= 3) {
                throw ValidationException::withMessages([
                    'day_id' => 'Máximo 3 ítems por día.',
                ]);
            }
        });
    }
}
