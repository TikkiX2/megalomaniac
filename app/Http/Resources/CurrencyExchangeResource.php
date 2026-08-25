<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyExchangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'from_currency_id' => $this->from_currency_id,
            'to_currency_id' => $this->to_currency_id,
            'from_amount' => $this->from_amount,
            'to_amount' => $this->to_amount,
            'exchange_rate' => $this->exchange_rate,
            'exchange_date' => $this->exchange_date?->toIso8601String(),
            'notes' => $this->notes,
            'withdrawal_id' => $this->withdrawal_id,
            'income_id' => $this->income_id,
            'to_reserve_id' => $this->to_reserve_id,
            'reserve_transaction_id' => $this->reserve_transaction_id,
            'from_currency' => new CurrencyResource($this->whenLoaded('fromCurrency')),
            'to_currency' => new CurrencyResource($this->whenLoaded('toCurrency')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
