<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'routine_id' => ['nullable', 'exists:routines,id'],
            'started_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'routine_id.exists' => 'The selected routine is invalid.',
            'started_at.required' => 'The start time is required.',
            'started_at.date' => 'The start time must be a valid date.',
        ];
    }
}
