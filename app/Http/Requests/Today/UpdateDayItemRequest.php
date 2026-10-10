<?php

namespace App\Http\Requests\Today;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDayItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('item')?->day?->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'state' => ['required', 'in:pending,done'],
            'closing_note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
