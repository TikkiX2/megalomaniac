<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'focus' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'exercise_ids' => ['nullable', 'array'],
            'exercise_ids.*' => ['exists:exercises,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The routine name is required.',
            'name.max' => 'The routine name must not exceed 255 characters.',
            'status.in' => 'The status must be active or archived.',
            'exercise_ids.*.exists' => 'One or more selected exercises are invalid.',
        ];
    }
}
