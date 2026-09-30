<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\IntakeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthMedicationIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'taken_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(IntakeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
