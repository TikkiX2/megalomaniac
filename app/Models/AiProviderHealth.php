<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderHealth extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'provider_id', 'consecutive_failures', 'last_success_at',
        'last_failure_at', 'broken_until', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'consecutive_failures' => 'integer',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'broken_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }
}
