<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalReflection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'week_start', 'body', 'source', 'model', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date:Y-m-d',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
