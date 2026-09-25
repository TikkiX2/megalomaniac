<?php

namespace App\Jobs;

use App\Ai\Documents\DocumentIndexer;
use App\Models\ChatAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexChatDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $attachmentId) {}

    public function handle(DocumentIndexer $indexer): void
    {
        $attachment = ChatAttachment::find($this->attachmentId);

        if ($attachment !== null && $attachment->kind === 'document') {
            $indexer->index($attachment);
        }
    }
}
