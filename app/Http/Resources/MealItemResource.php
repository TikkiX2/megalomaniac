<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meal_log_id' => $this->meal_log_id,
            'food_id' => $this->food_id,
            'food' => new FoodResource($this->whenLoaded('food')),
            'quantity' => $this->quantity,
            'calories_snapshot' => $this->calories_snapshot,
            'protein_snapshot' => $this->protein_snapshot,
            'carbs_snapshot' => $this->carbs_snapshot,
            'fats_snapshot' => $this->fats_snapshot,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
