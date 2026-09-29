<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\PersonInteractionObserver;
use App\People\Enums\InteractionChannel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([PersonInteractionObserver::class])]
class PersonInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'channel', 'occurred_at', 'title', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'channel' => InteractionChannel::class,
            'occurred_at' => 'datetime',
        ];
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
