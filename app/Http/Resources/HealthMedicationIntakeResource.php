<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthMedicationIntakeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'taken_at' => $this->taken_at?->toIso8601String(),
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'medication' => new HealthMedicationResource($this->whenLoaded('medication')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
