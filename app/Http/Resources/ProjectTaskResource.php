<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'responsible' => $this->responsible,
            'urgency' => $this->urgency,
            'importance' => $this->importance,
            'priority' => $this->priority,
            'module' => $this->module,
            'tags' => $this->tags,
            'area' => $this->area,
            'due_date' => $this->due_date?->toIso8601String(),
            'start_date' => $this->start_date?->toIso8601String(),
            'estimated_time' => $this->estimated_time,
            'actual_time' => $this->actual_time,
            'sort_order' => $this->sort_order,
            'is_archived' => $this->is_archived,
            'properties' => TaskPropertyResource::collection($this->whenLoaded('properties')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
