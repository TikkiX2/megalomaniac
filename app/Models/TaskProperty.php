<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskProperty extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_task_id',
        'key',
        'type',
        'value_text',
        'value_number',
        'value_date',
        'value_json',
        'sort_order',
    ];

    protected $casts = [
        'value_json' => 'array',
        'value_date' => 'date',
        'value_number' => 'decimal:2',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'project_task_id');
    }

    public function getValueAttribute(): mixed
    {
        return match ($this->type) {
            'text', 'url', 'person' => $this->value_text,
            'number' => $this->value_number,
            'date' => $this->value_date,
            'select', 'multi_select', 'checkbox' => $this->value_json,
            default => $this->value_text ?? $this->value_json,
        };
    }
}
