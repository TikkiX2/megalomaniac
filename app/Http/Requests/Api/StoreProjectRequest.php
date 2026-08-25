<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable'],
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,maintenance,archived'],
            'type' => ['required', 'string', 'in:freelance,personal'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'area' => ['nullable', 'string'],
            'module' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'urgency' => ['nullable', 'string'],
            'importance' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'is_archived' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string'],
            'icon' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The project name is required.',
            'type.required' => 'The project type is required.',
            'type.in' => 'The project type must be either freelance or personal.',
        ];
    }
}
