<?php

namespace App\Http\Requests\TaskBoardColumn;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskBoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:80'],
            'color' => ['sometimes', 'string', 'max:20'],
            'is_done' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
