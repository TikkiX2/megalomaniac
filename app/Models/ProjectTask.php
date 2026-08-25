<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'description',
        'status',
        'responsible',
        'urgency',
        'importance',
        'priority',
        'module',
        'tags',
        'area',
        'due_date',
        'start_date',
        'estimated_time',
        'actual_time',
        'sort_order',
        'is_archived',
        'notion_page_id',
        'notion_last_sync',
    ];

    protected $casts = [
        'description' => 'array',
        'tags' => 'array',
        'due_date' => 'date',
        'start_date' => 'date',
        'is_archived' => 'boolean',
        'notion_last_sync' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(TaskProperty::class)->orderBy('sort_order');
    }

    public function scopePending($query)
    {
        return $query->where('status', '!=', 'Done')->where('status', '!=', 'Completed');
    }

    public function scopePersonal($query)
    {
        return $query->whereHas('project', fn ($q) => $q->where('type', 'personal'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
