<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\MeasurementType;
use Illuminate\Validation\Rule;

class UpdateHealthMeasurementRequest extends StoreHealthMeasurementRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['sometimes', Rule::enum(MeasurementType::class)],
            'value' => ['sometimes', 'numeric'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'measured_at' => ['sometimes', 'date'],
        ]);
    }
}
