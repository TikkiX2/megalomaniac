<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'purchase_id' => $this->purchase_id,
            'credit_card_id' => $this->credit_card_id,
            'currency_id' => $this->currency_id,
            'original_amount' => $this->original_amount,
            'remaining_amount' => $this->remaining_amount,
            'interest_amount' => $this->interest_amount,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'due_date' => $this->due_date?->toIso8601String(),
            'status' => $this->status,
            'notes' => $this->notes,
            'payments' => DebtPaymentResource::collection($this->whenLoaded('payments')),
            'credit_card' => new CreditCardResource($this->whenLoaded('creditCard')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
