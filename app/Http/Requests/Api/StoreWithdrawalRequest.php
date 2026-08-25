<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:withdrawal_categories,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'withdrawal_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string'],
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
            'category_id.exists' => 'The selected category is invalid.',
            'currency_id.exists' => 'The selected currency is invalid.',
            'withdrawal_date.required' => 'The withdrawal date is required.',
            'withdrawal_date.date' => 'The withdrawal date must be a valid date.',
        ];
    }
}
