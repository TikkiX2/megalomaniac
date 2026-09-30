<?php

namespace App\Http\Requests\Api;

class UpdateHealthMedicationRequest extends StoreHealthMedicationRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'name' => ['sometimes', 'string', 'max:255'],
        ]);
    }
}
