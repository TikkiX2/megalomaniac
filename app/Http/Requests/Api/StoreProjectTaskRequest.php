<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable'],
            'status' => ['sometimes', 'string'],
            'responsible' => ['nullable', 'string'],
            'urgency' => ['nullable', 'string'],
            'importance' => ['nullable', 'string'],
            'priority' => ['nullable', 'string'],
            'module' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'area' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'estimated_time' => ['nullable', 'numeric', 'min:0'],
            'actual_time' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'is_archived' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'The project is required.',
            'project_id.exists' => 'The selected project does not exist.',
            'title.required' => 'The task title is required.',
        ];
    }
}
