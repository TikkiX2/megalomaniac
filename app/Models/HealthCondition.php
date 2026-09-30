<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'kind', 'name', 'status', 'severity',
        'diagnosed_at', 'provider_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ConditionKind::class,
            'status' => ConditionStatus::class,
            'severity' => Severity::class,
            'diagnosed_at' => 'date',
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

    public function provider(): BelongsTo
    {
        return $this->belongsTo(HealthProfessional::class, 'provider_id');
    }
}
