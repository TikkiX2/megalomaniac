<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'avatar_url' => $this->avatar_url,
            'birthday' => $this->birthday?->toDateString(),
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'website' => $this->website,
            'how_we_met' => $this->how_we_met,
            'closeness' => $this->closeness?->value,
            'relationship_status' => $this->relationship_status?->value,
            'preferred_contact_channel' => $this->preferred_contact_channel?->value,
            'is_favorite' => $this->is_favorite,
            'is_archived' => $this->is_archived,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'notes' => $this->notes,
            'key_dates' => PersonKeyDateResource::collection($this->whenLoaded('keyDates')),
            'socials' => PersonSocialResource::collection($this->whenLoaded('socials')),
            'interactions' => PersonInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
