<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyExchangeRequest extends FormRequest
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
            'from_amount' => ['required', 'numeric', 'min:0'],
            'to_amount' => ['required', 'numeric', 'min:0'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'exchange_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'withdrawal_id' => ['nullable', 'exists:withdrawals,id'],
            'income_id' => ['nullable', 'exists:incomes,id'],
            'to_reserve_id' => ['nullable', 'exists:savings_reserves,id'],
            'reserve_transaction_id' => ['nullable', 'exists:reserve_transactions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency_id.required' => 'The source currency is required.',
            'to_currency_id.required' => 'The target currency is required.',
            'to_currency_id.different' => 'The target currency must be different from the source.',
            'from_amount.required' => 'The source amount is required.',
            'from_amount.numeric' => 'The source amount must be a number.',
            'to_amount.required' => 'The target amount is required.',
            'to_amount.numeric' => 'The target amount must be a number.',
            'exchange_rate.required' => 'The exchange rate is required.',
            'exchange_rate.numeric' => 'The exchange rate must be a number.',
            'exchange_date.required' => 'The exchange date is required.',
        ];
    }
}
