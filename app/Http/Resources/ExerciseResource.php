<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'muscle_group' => $this->muscle_group,
            'type' => $this->type,
            'video_url' => $this->video_url,
        ];
    }
}
