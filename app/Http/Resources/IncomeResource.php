<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'income_source_id' => $this->income_source_id,
            'currency_id' => $this->currency_id,
            'amount' => $this->amount,
            'received_date' => $this->received_date?->toIso8601String(),
            'description' => $this->description,
            'is_recurring' => $this->is_recurring,
            'recurrence_day' => $this->recurrence_day,
            'recurrence_end_date' => $this->recurrence_end_date?->toIso8601String(),
            'metadata' => $this->metadata,
            'income_source' => new IncomeSourceResource($this->whenLoaded('incomeSource')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
