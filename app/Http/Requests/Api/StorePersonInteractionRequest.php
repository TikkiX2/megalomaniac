<?php

namespace App\Http\Requests\Api;

use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
