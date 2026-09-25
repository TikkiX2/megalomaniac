<?php

namespace App\Models;

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id', 'user_id', 'action_key', 'params', 'access', 'summary',
        'rationale', 'status', 'decided_at', 'decided_by', 'decision_note',
        'executed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'access' => ActionAccess::class,
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function log(): HasOne
    {
        return $this->hasOne(IntegrationActionLog::class);
    }

    public function requester(): MorphTo
    {
        return $this->morphTo('requested_by');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }
}
