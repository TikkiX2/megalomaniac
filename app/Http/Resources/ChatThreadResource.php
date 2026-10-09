<?php

namespace App\Http\Resources;

use App\Ai\Services\ChatService;
use App\Models\ChatThread;
use App\Models\HealthAppointment;
use App\Models\HealthCondition;
use App\Models\HealthStudy;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model' => $this->model,
            'mode' => app(ChatService::class)->sourceMode($this->resource),
            'deep_context' => (bool) ($this->deep_context ?? false),
            'tools_policy' => $this->tools_policy,
            'is_pinned' => $this->pinned_at !== null,
            'memories_count' => $this->whenCounted('memories'),
            'category' => $this->category ?? ChatThread::CATEGORY_GENERAL,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'context_label' => $this->whenLoaded('context', fn (): ?string => match (true) {
                $this->context instanceof HealthCondition => $this->context->name,
                $this->context instanceof HealthStudy => $this->context->title,
                $this->context instanceof HealthAppointment => $this->context->title,
                $this->context instanceof Person => $this->context->full_name,
                default => null,
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
