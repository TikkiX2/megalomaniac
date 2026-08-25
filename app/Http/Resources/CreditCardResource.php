<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'last_four_digits' => $this->last_four_digits,
            'owner_id' => $this->owner_id,
            'is_mine' => $this->is_mine,
            'interest_rate' => $this->interest_rate,
            'tax_percentage' => $this->tax_percentage,
            'apply_interest' => $this->apply_interest,
            'apply_tax' => $this->apply_tax,
            'notes' => $this->notes,
            'debts' => DebtResource::collection($this->whenLoaded('debts')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
