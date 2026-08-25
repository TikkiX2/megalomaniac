<?php

namespace App\Http\Requests\Personal;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'due_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            'estimated_time' => ['nullable', 'integer', 'min:0'],
            'actual_time' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'area' => ['nullable', 'string', 'max:100'],
            'module' => ['nullable', 'string', 'max:100'],
            'urgency' => ['nullable', 'string', 'max:50'],
            'importance' => ['nullable', 'string', 'max:50'],
            'responsible' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
            'is_archived' => ['nullable', 'boolean'],
        ];
    }
}
