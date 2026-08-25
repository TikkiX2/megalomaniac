<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroceryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'category' => $this->category,
            'current_stock' => $this->current_stock,
            'target_stock' => $this->target_stock,
            'unit' => $this->unit,
            'price' => $this->price,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'price_history' => GroceryPriceHistoryResource::collection($this->whenLoaded('priceHistory')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
