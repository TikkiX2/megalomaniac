<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'focus' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,archived'],
            'exercise_ids' => ['nullable', 'array'],
            'exercise_ids.*' => ['exists:exercises,id'],
        ];
    }
}
