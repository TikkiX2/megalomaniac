<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'dosage_amount' => ['required', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The supplement name is required.',
            'name.max' => 'The supplement name must not exceed 255 characters.',
            'dosage_amount.required' => 'The dosage amount is required.',
            'dosage_amount.max' => 'The dosage amount must not exceed 255 characters.',
            'image_url.url' => 'The image URL must be a valid URL.',
        ];
    }
}
