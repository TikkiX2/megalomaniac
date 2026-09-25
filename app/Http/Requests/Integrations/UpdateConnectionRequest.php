<?php

namespace App\Http\Requests\Integrations;

use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\TransportKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConnectionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'auth_type' => ['sometimes', Rule::enum(AuthType::class)],
            'credentials' => ['sometimes', 'nullable', 'array'],
            'base_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'transport' => ['sometimes', Rule::enum(TransportKind::class)],
            'transport_config' => ['sometimes', 'nullable', 'array'],
            'options' => ['sometimes', 'nullable', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
