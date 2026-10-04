<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source
 * @property string $kind
 * @property string $query_hash
 * @property array<string, mixed> $payload
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 */
class InspirationCacheEntry extends Model
{
    /**
     * The cache table has no created_at/updated_at columns.
     */
    public $timestamps = false;

    protected $table = 'inspiration_cache';

    protected $fillable = [
        'source',
        'kind',
        'query_hash',
        'payload',
        'fetched_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
