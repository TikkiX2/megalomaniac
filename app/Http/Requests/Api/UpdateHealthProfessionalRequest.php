<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\ProfessionalType;
use Illuminate\Validation\Rule;

class UpdateHealthProfessionalRequest extends StoreHealthProfessionalRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['sometimes', Rule::enum(ProfessionalType::class)],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);
    }
}
