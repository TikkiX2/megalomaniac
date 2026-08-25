<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'income_id' => $this->income_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'expected_date' => $this->expected_date?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
