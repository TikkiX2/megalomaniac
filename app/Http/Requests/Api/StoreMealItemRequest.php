<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreMealItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'food_id' => ['required', 'exists:foods,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'food_id.required' => 'The food ID is required.',
            'food_id.exists' => 'The selected food does not exist.',
            'quantity.required' => 'The quantity is required.',
            'quantity.numeric' => 'The quantity must be a number.',
            'quantity.min' => 'The quantity must be at least 0.01.',
        ];
    }
}
