<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreGroceryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'current_stock' => ['nullable', 'numeric', 'min:0'],
            'target_stock' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The grocery item name is required.',
            'name.max' => 'The grocery item name must not exceed 255 characters.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must not be negative.',
        ];
    }
}
