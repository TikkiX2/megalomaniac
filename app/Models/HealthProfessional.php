<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\ProfessionalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthProfessional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'name', 'specialty', 'phone', 'email',
        'address', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProfessionalType::class,
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(HealthCondition::class, 'provider_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(HealthMedication::class, 'prescriber_id');
    }
}
