<?php

namespace App\Http\Requests\Api;

use App\People\Enums\KeyDateType;
use Illuminate\Validation\Rule;

class UpdatePersonKeyDateRequest extends StorePersonKeyDateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'type' => ['sometimes', Rule::enum(KeyDateType::class)],
            'date' => ['sometimes', 'date'],
        ]);
    }
}
