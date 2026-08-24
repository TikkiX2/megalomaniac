<?php

namespace App\Http\Requests\Finance;

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
            'currency_id' => ['required', 'exists:currencies,id'],
            'category_id' => ['nullable', 'exists:purchase_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'purchase_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'image', 'max:5120'], // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'currency_id.required' => 'La moneda es obligatoria.',
            'currency_id.exists' => 'La moneda seleccionada no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'purchase_date.required' => 'La fecha de compra es obligatoria.',
            'purchase_date.date' => 'La fecha de compra no es válida.',
            'description.required' => 'La descripción es obligatoria.',
            'receipt.image' => 'El recibo debe ser una imagen.',
            'receipt.max' => 'El recibo no puede superar los 5MB.',
        ];
    }
}
