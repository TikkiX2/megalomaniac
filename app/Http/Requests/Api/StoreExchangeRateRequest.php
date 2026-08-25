<?php

namespace App\Http\Requests\Api;

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
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency_id.required' => 'The source currency is required.',
            'to_currency_id.required' => 'The target currency is required.',
            'to_currency_id.different' => 'The target currency must be different from the source.',
            'rate.required' => 'The exchange rate is required.',
            'rate.numeric' => 'The exchange rate must be a number.',
            'effective_date.required' => 'The effective date is required.',
        ];
    }
}
