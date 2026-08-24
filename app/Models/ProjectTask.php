<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
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
        'notion_page_id',
        'notion_last_sync',
    ];

    protected $casts = [
        'description' => 'array',
        'tags' => 'array',
        'due_date' => 'date',
        'notion_last_sync' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', '!=', 'Done')->where('status', '!=', 'Completed');
    }
}
