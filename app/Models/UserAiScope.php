<?php

namespace App\Models;

use Database\Factories\UserAiScopeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAiScope extends Model
{
    /** @use HasFactory<UserAiScopeFactory> */
    use HasFactory;

    protected $table = 'ai_scopes';

    protected $fillable = [
        'user_id', 'scope', 'provider_chain', 'prompt',
    ];

    protected function casts(): array
    {
        return [
            'provider_chain' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
