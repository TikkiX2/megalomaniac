<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthMedicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'name' => $this->name,
            'dose_amount' => $this->dose_amount,
            'dose_unit' => $this->dose_unit,
            'route' => $this->route,
            'frequency_text' => $this->frequency_text,
            'started_at' => $this->started_at?->toDateString(),
            'ended_at' => $this->ended_at?->toDateString(),
            'is_active' => $this->is_active,
            'condition_id' => $this->condition_id,
            'prescriber_id' => $this->prescriber_id,
            'notes' => $this->notes,
            'person' => new PersonResource($this->whenLoaded('person')),
            'condition' => new HealthConditionResource($this->whenLoaded('condition')),
            'prescriber' => new HealthProfessionalResource($this->whenLoaded('prescriber')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
