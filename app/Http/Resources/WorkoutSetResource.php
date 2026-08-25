<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workout_exercise_id' => $this->workout_exercise_id,
            'set_number' => $this->set_number,
            'weight' => $this->weight,
            'reps' => $this->reps,
            'rpe' => $this->rpe,
            'completed' => $this->completed,
        ];
    }
}
