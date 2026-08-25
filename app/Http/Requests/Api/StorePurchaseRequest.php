<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:purchase_categories,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'purchase_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'The amount is required.',
            'amount.numeric' => 'The amount must be a number.',
            'category_id.exists' => 'The selected category is invalid.',
            'currency_id.exists' => 'The selected currency is invalid.',
            'purchase_date.required' => 'The purchase date is required.',
            'purchase_date.date' => 'The purchase date must be a valid date.',
        ];
    }
}
