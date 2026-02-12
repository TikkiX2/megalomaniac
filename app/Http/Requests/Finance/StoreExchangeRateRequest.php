<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_currency_id' => ['required', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'exists:currencies,id', 'different:from_currency_id'],
            'rate' => ['required', 'numeric', 'min:0.00000001'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency_id.required' => 'La moneda origen es obligatoria.',
            'to_currency_id.required' => 'La moneda destino es obligatoria.',
            'to_currency_id.different' => 'La moneda destino debe ser diferente a la de origen.',
            'rate.required' => 'La tasa de cambio es obligatoria.',
            'rate.min' => 'La tasa debe ser mayor a 0.',
            'effective_date.required' => 'La fecha efectiva es obligatoria.',
        ];
    }
}
