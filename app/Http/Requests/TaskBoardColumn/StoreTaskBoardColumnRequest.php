<?php

namespace App\Http\Requests\TaskBoardColumn;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskBoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'label' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'is_done' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'El nombre de la columna es obligatorio.',
            'project_id.exists' => 'El proyecto seleccionado no existe.',
        ];
    }
}
