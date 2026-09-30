<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthSymptomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'symptom' => $this->symptom,
            'severity' => $this->severity?->value,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'notes' => $this->notes,
            'person' => new PersonResource($this->whenLoaded('person')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
