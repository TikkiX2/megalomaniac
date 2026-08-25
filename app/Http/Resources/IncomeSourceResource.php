<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeSourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'description' => $this->description,
            'default_currency_id' => $this->default_currency_id,
            'is_active' => $this->is_active,
            'default_currency' => new CurrencyResource($this->whenLoaded('defaultCurrency')),
            'incomes' => IncomeResource::collection($this->whenLoaded('incomes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
