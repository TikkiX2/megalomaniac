<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingsReserveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'currency_id' => $this->currency_id,
            'name' => $this->name,
            'description' => $this->description,
            'goal_amount' => $this->goal_amount,
            'current_amount' => $this->current_amount,
            'target_date' => $this->target_date?->toIso8601String(),
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'progress' => $this->whenLoaded('currency') ? $this->progress() : null,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'transactions' => ReserveTransactionResource::collection($this->whenLoaded('transactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
