<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReserveTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reserve_id' => $this->reserve_id,
            'currency_id' => $this->currency_id,
            'amount' => $this->amount,
            'transaction_type' => $this->transaction_type,
            'transaction_date' => $this->transaction_date?->toIso8601String(),
            'description' => $this->description,
            'notes' => $this->notes,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
