<?php

namespace App\Http\Requests\Api;

use App\People\Enums\Closeness;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends StorePersonRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'closeness' => ['sometimes', Rule::enum(Closeness::class)],
        ]);
    }
}
