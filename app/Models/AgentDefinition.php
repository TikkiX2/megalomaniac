<?php

namespace App\Models;

use Database\Factories\AgentDefinitionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AgentDefinition extends Model
{
    /** @use HasFactory<AgentDefinitionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'key', 'name', 'description', 'instructions', 'tools_policy',
        'schedule_type', 'schedule_value', 'timezone', 'enabled', 'max_runs_per_day',
        'max_tokens_per_run', 'last_run_at', 'next_run_at', 'failure_count',
        'created_by_type', 'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'tools_policy' => 'array',
            'enabled' => 'boolean',
            'max_runs_per_day' => 'integer',
            'max_tokens_per_run' => 'integer',
            'failure_count' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agentRuns(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->enabled()
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    public function isRunning(): bool
    {
        return $this->agentRuns()
            ->whereIn('status', [AgentRun::STATUS_QUEUED, AgentRun::STATUS_RUNNING])
            ->exists();
    }

    public function runsToday(): int
    {
        return $this->agentRuns()
            ->whereDate('created_at', now()->toDateString())
            ->whereNotIn('status', [AgentRun::STATUS_SKIPPED])
            ->count();
    }
}
