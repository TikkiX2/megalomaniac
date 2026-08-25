<?php

namespace App\Http\Requests\Personal;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonalTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
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
            'properties' => ['nullable', 'array'],
            'properties.*.key' => ['required_with:properties', 'string', 'max:100'],
            'properties.*.type' => ['required_with:properties', 'in:text,number,date,select,multi_select,checkbox,url,person'],
            'properties.*.value' => ['nullable'],
            'properties.*.value_text' => ['nullable', 'string'],
            'properties.*.value_number' => ['nullable', 'numeric'],
            'properties.*.value_date' => ['nullable', 'date'],
            'properties.*.value_json' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'project_id.exists' => 'El proyecto seleccionado no existe.',
            'start_date.before_or_equal' => 'La fecha de inicio debe ser anterior a la fecha de vencimiento.',
        ];
    }
}
