<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'type' => $this->type,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'deadline' => $this->deadline?->toIso8601String(),
            'total_amount' => $this->total_amount,
            'currency_id' => $this->currency_id,
            'paid_amount' => $this->paid_amount,
            'hourly_rate' => $this->hourly_rate,
            'estimated_hours' => $this->estimated_hours,
            'area' => $this->area,
            'module' => $this->module,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'importance' => $this->importance,
            'tags' => $this->tags,
            'notes' => $this->notes,
            'is_archived' => $this->is_archived,
            'color' => $this->color,
            'icon' => $this->icon,
            'budget' => $this->budget,
            'progress' => $this->when(fn () => $this->relationLoaded('tasks') || $this->relationLoaded('milestones'), $this->progress),
            'client' => new ClientResource($this->whenLoaded('client')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'tasks' => ProjectTaskResource::collection($this->whenLoaded('tasks')),
            'payments' => ProjectPaymentResource::collection($this->whenLoaded('payments')),
            'comments' => ProjectCommentResource::collection($this->whenLoaded('comments')),
            'milestones' => TaskMilestoneResource::collection($this->whenLoaded('milestones')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
