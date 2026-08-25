<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'currency_id' => $this->currency_id,
            'amount' => $this->amount,
            'purchase_date' => $this->purchase_date?->toIso8601String(),
            'description' => $this->description,
            'notes' => $this->notes,
            'receipt_path' => $this->receipt_path,
            'category' => new PurchaseCategoryResource($this->whenLoaded('category')),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'debt' => new DebtResource($this->whenLoaded('debt')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
