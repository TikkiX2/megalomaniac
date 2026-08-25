<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'The currency code is required.',
            'code.size' => 'The currency code must be 3 characters.',
            'code.unique' => 'A currency with this code already exists.',
            'name.required' => 'The currency name is required.',
            'symbol.required' => 'The currency symbol is required.',
        ];
    }
}
