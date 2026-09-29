<?php

namespace App\Http\Requests\Api;

class UpdatePersonSocialRequest extends StorePersonSocialRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'network' => ['sometimes', 'string', 'max:50'],
        ]);
    }
}
