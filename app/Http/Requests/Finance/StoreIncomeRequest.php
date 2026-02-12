<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'income_source_id' => ['required', 'exists:income_sources,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_recurring' => ['boolean'],
            'recurrence_day' => ['nullable', 'required_if:is_recurring,true', 'integer', 'min:1', 'max:31'],
            'recurrence_end_date' => ['nullable', 'date', 'after:received_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'income_source_id.required' => 'La fuente de ingreso es obligatoria.',
            'income_source_id.exists' => 'La fuente de ingreso seleccionada no es válida.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'currency_id.exists' => 'La moneda seleccionada no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'received_date.required' => 'La fecha de recepción es obligatoria.',
            'received_date.date' => 'La fecha de recepción no es válida.',
            'recurrence_day.required_if' => 'El día de recurrencia es obligatorio para ingresos recurrentes.',
            'recurrence_day.min' => 'El día debe ser entre 1 y 31.',
            'recurrence_day.max' => 'El día debe ser entre 1 y 31.',
            'recurrence_end_date.after' => 'La fecha de fin debe ser posterior a la fecha de recepción.',
        ];
    }
}
