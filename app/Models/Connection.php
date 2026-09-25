<?php

namespace App\Models;

use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Enums\TransportKind;
use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'kind', 'name', 'auth_type', 'credentials', 'base_url',
        'transport', 'transport_config', 'options', 'enabled', 'status',
        'status_message', 'last_tested_at', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'transport_config' => 'encrypted:array',
            'options' => 'array',
            'enabled' => 'boolean',
            'auth_type' => AuthType::class,
            'transport' => TransportKind::class,
            'status' => ConnectionStatus::class,
            'last_tested_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(IntegrationActionLog::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
