<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use Illuminate\Validation\Rule;

class UpdateHealthConditionRequest extends StoreHealthConditionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'kind' => ['sometimes', Rule::enum(ConditionKind::class)],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(ConditionStatus::class)],
        ]);
    }
}
