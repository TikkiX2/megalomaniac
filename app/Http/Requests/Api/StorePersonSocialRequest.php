<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StorePersonSocialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'network' => ['required', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
