<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\Severity;
use Illuminate\Validation\Rule;

class UpdateHealthSymptomRequest extends StoreHealthSymptomRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'symptom' => ['sometimes', 'string', 'max:255'],
            'severity' => ['sometimes', Rule::enum(Severity::class)],
            'occurred_at' => ['sometimes', 'date'],
        ]);
    }
}
