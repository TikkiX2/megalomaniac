<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatAttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'status' => $this->status,
            'error' => $this->error,
            'is_image' => $this->kind === 'image',
            'url' => route('ai.chat.attachments.show', $this),
        ];
    }
}
