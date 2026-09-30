<?php

namespace App\Models;

use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'protocol', 'url', 'key', 'model',
        'embeddings_model', 'enabled', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'key' => 'encrypted',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function health(): HasOne
    {
        // The column is `provider_id`, not the `ai_provider_id` convention.
        return $this->hasOne(AiProviderHealth::class, 'provider_id');
    }

    public function scopeAssignments(): HasMany
    {
        return $this->hasMany(UserAiScope::class);
    }
}
