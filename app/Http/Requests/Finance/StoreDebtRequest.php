<?php

namespace App\Http\Requests\Finance;

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
            'currency_id' => ['required', 'exists:currencies,id'],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_id.required' => 'La compra es obligatoria.',
            'purchase_id.exists' => 'La compra seleccionada no es válida.',
            'credit_card_id.required' => 'La tarjeta de crédito es obligatoria.',
            'credit_card_id.exists' => 'La tarjeta de crédito seleccionada no es válida.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'currency_id.exists' => 'La moneda seleccionada no es válida.',
            'original_amount.required' => 'El monto es obligatorio.',
            'original_amount.numeric' => 'El monto debe ser un número.',
            'original_amount.min' => 'El monto debe ser mayor a 0.',
            'due_date.date' => 'La fecha de vencimiento no es válida.',
            'due_date.after' => 'La fecha de vencimiento debe ser futura.',
        ];
    }
}
