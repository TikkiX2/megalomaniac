<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'date' => $this->date?->format('Y-m-d'),
            'meal_type' => $this->meal_type,
            'items' => MealItemResource::collection($this->whenLoaded('items')),
            'total_calories' => $this->total_calories,
            'total_macros' => $this->total_macros,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
