<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueItem extends Model
{
    use HasFactory;

    protected $table = 'queue_items';

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'position',
        'source',
        'external_id',
        'cover_url',
        'year',
        'creator',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'year' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
