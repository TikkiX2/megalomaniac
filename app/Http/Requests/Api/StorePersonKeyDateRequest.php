<?php

namespace App\Http\Requests\Api;

use App\People\Enums\KeyDateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonKeyDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KeyDateType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ];
    }
}
