<?php

namespace App\Http\Requests\Hoy;

use App\Models\DiaItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreMananaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array', 'max:3'],
            'items.*.tarea_id' => ['nullable', 'exists:project_tasks,id'],
            'items.*.titulo' => ['required_with:items', 'string', 'max:255'],
            'items.*.ancla' => ['required_with:items', 'in:'.implode(',', DiaItem::ANCLAS)],
            'items.*.posicion' => ['required_with:items', 'integer', 'min:1', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.max' => 'Máximo 3 ítems por día.',
        ];
    }
}
