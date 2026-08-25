<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'routine_id' => $this->routine_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'notes' => $this->notes,
            'duration_minutes' => $this->started_at && $this->ended_at
                ? $this->started_at->diffInMinutes($this->ended_at)
                : null,
            'exercises' => WorkoutExerciseResource::collection(
                $this->whenLoaded('exercises')
            ),
            'routine' => new RoutineResource(
                $this->whenLoaded('routine')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
