<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCreditCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'last_four_digits' => ['required', 'string', 'size:4'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'is_mine' => ['sometimes', 'boolean'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'tax_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'apply_interest' => ['sometimes', 'boolean'],
            'apply_tax' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name is required.',
            'last_four_digits.required' => 'The last four digits are required.',
            'last_four_digits.size' => 'The last four digits must be 4 characters.',
        ];
    }
}
