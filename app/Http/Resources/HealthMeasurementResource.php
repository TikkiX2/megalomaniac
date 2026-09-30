<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthMeasurementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'type' => $this->type?->value,
            'value' => $this->value,
            'secondary_value' => $this->secondary_value,
            'unit' => $this->unit,
            'measured_at' => $this->measured_at?->toIso8601String(),
            'notes' => $this->notes,
            'person' => new PersonResource($this->whenLoaded('person')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
