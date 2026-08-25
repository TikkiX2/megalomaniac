<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'quote_number' => $this->quote_number,
            'title' => $this->title,
            'issue_date' => $this->issue_date?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'tax_percentage' => $this->tax_percentage,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'currency_id' => $this->currency_id,
            'hourly_rate' => $this->hourly_rate,
            'notes' => $this->notes,
            'terms_and_conditions' => $this->terms_and_conditions,
            'items' => QuoteItemResource::collection($this->whenLoaded('items')),
            'client' => new ClientResource($this->whenLoaded('client')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
