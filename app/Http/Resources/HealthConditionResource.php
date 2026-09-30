<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind?->value,
            'name' => $this->name,
            'status' => $this->status?->value,
            'severity' => $this->severity?->value,
            'diagnosed_at' => $this->diagnosed_at?->toDateString(),
            'provider_id' => $this->provider_id,
            'person_id' => $this->person_id,
            'notes' => $this->notes,
            'person' => new PersonResource($this->whenLoaded('person')),
            'provider' => new HealthProfessionalResource($this->whenLoaded('provider')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
