<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encrypted per-source session credential for the inspiration module.
 *
 * The `data` payload is encrypted at rest with the application key; plain
 * cookies or passwords never reach the database or the response payloads.
 */
class InspirationAuth extends Model
{
    public $timestamps = false;

    protected $table = 'inspiration_auth';

    protected $fillable = ['user_id', 'source', 'type', 'data', 'invalid'];

    protected $casts = [
        'invalid' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
