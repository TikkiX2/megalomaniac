<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations(),
            'reasoning' => $this->meta['reasoning'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
