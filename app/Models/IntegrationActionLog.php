<?php

namespace App\Models;

use App\Integrations\Enums\ActionAccess;
use Database\Factories\IntegrationActionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationActionLog extends Model
{
    /** @use HasFactory<IntegrationActionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id', 'user_id', 'approval_request_id', 'action_key', 'access',
        'actor_type', 'actor_id', 'source', 'params', 'result_summary', 'status',
        'error', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'access' => ActionAccess::class,
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }
}
