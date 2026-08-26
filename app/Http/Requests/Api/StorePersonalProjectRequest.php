<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable'],
            'status' => ['sometimes', 'string', 'in:pending,in_progress,completed,maintenance,archived'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
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
            'milestones' => ['nullable', 'array'],
            'milestones.*.name' => ['required_with:milestones', 'string'],
            'milestones.*.description' => ['nullable', 'string'],
            'milestones.*.due_date' => ['nullable', 'date'],
            'milestones.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The project name is required.',
        ];
    }
}
