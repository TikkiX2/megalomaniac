<?php

namespace App\Http\Requests\Today;

use App\Models\DayItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreTomorrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['nullable', 'array', 'max:3'],
            'items.*.task_id' => ['nullable', 'exists:project_tasks,id'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.anchor' => ['required_with:items', 'in:'.implode(',', DayItem::ANCHORS)],
            'items.*.position' => ['required_with:items', 'integer', 'min:1', 'max:3'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.max' => 'Máximo 3 ítems por día.',
        ];
    }
}
