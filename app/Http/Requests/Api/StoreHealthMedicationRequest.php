<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'dose_amount' => ['nullable', 'numeric', 'min:0'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'route' => ['nullable', 'string', 'max:30'],
            'frequency_text' => ['nullable', 'string', 'max:255'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'is_active' => ['sometimes', 'boolean'],
            'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $this->user()->id)],
            'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
