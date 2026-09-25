<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatDocumentChunk extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ChatAttachment::class, 'attachment_id');
    }
}
