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
            'pending_approvals' => collect($this->approval_state['pending'] ?? [])
                ->map(function (string $reason, string $toolCallId): array {
                    $call = collect($this->tool_calls ?? [])->firstWhere('id', $toolCallId);
                    $tool = data_get($call, 'name') ?? data_get($call, 'function.name') ?? 'tool';

                    return [
                        'id' => $toolCallId,
                        'tool' => $tool,
                        'arguments' => data_get($call, 'arguments') ?? data_get($call, 'function.arguments') ?? [],
                        'reason' => $reason,
                        'kind' => $tool === 'AskUserTool' ? 'question' : 'approval',
                    ];
                })
                ->values()
                ->all(),
            'attachments' => ChatAttachmentResource::collection(
                $this->getRelationValue('attachments')
            )->resolve($request),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
