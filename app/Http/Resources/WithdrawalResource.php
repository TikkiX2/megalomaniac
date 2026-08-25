<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'currency_id' => $this->currency_id,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'withdrawal_date' => $this->withdrawal_date?->toIso8601String(),
            'description' => $this->description,
            'notes' => $this->notes,
            'is_recurring' => $this->is_recurring,
            'recurrence_frequency' => $this->recurrence_frequency,
            'recurrence_day' => $this->recurrence_day,
            'recurrence_end_date' => $this->recurrence_end_date?->toIso8601String(),
            'receipt_path' => $this->receipt_path,
            'metadata' => $this->metadata,
            'category' => new WithdrawalCategoryResource($this->whenLoaded('category')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
