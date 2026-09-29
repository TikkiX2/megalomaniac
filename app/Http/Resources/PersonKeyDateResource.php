<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonKeyDateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'type' => $this->type?->value,
            'label' => $this->display_label,
            'date' => $this->date?->toDateString(),
            'remind_days_before' => $this->remind_days_before,
            'is_recurring_annually' => $this->is_recurring_annually,
        ];
    }
}
