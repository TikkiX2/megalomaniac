<?php

declare(strict_types=1);

namespace App\Models;

use App\People\Enums\KeyDateType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonKeyDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'type', 'label', 'date',
        'remind_days_before', 'is_recurring_annually',
    ];

    protected function casts(): array
    {
        return [
            'type' => KeyDateType::class,
            'date' => 'date',
            'is_recurring_annually' => 'boolean',
        ];
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: ucfirst(str_replace('_', ' ', $this->type->value));
    }

    public function nextOccurrence(): ?CarbonInterface
    {
        if (! $this->is_recurring_annually) {
            return $this->date->isToday() || $this->date->isFuture()
                ? $this->date->copy()
                : null;
        }

        $candidate = $this->date->copy()->setYear((int) now()->year);

        if ($candidate->lt(now()->startOfDay())) {
            $candidate = $candidate->addYear();
        }

        return $candidate;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
