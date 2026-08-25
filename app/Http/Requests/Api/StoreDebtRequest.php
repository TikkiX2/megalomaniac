<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'credit_card_id' => ['nullable', 'exists:credit_cards,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'original_amount' => ['required', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'interest_amount' => ['nullable', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'original_amount.required' => 'The original amount is required.',
            'original_amount.numeric' => 'The original amount must be a number.',
            'total_amount.required' => 'The total amount is required.',
            'total_amount.numeric' => 'The total amount must be a number.',
            'due_date.required' => 'The due date is required.',
            'due_date.date' => 'The due date must be a valid date.',
        ];
    }
}
