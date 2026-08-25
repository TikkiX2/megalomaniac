<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskSavedView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'view_type',
        'filters',
        'sort',
        'group_by',
        'is_default',
    ];

    protected $casts = [
        'filters' => 'array',
        'sort' => 'array',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
