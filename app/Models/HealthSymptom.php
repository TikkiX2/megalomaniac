<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthSymptom extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'person_id', 'symptom', 'severity', 'occurred_at', 'notes'];

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
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
