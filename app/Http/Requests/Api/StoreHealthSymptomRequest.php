<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthSymptomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symptom' => ['required', 'string', 'max:255'],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'occurred_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
