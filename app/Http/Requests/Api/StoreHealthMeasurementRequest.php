<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\MeasurementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MeasurementType::class)],
            'value' => ['required', 'numeric'],
            'secondary_value' => ['nullable', 'numeric'],
            'unit' => ['required', 'string', 'max:20'],
            'measured_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
