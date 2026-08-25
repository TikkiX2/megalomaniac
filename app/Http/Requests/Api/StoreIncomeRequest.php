<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'income_source_id' => ['nullable', 'exists:income_sources,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'received_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_day' => ['nullable', 'integer'],
            'recurrence_end_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a number.',
            'income_source_id.exists' => 'The selected income source is invalid.',
            'currency_id.exists' => 'The selected currency is invalid.',
            'received_date.required' => 'The received date is required.',
            'received_date.date' => 'The received date must be a valid date.',
        ];
    }
}
