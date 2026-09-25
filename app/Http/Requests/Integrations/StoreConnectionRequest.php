<?php

namespace App\Http\Requests\Integrations;

use App\Integrations\ConnectorRegistry;
use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\TransportKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConnectionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_keys(app(ConnectorRegistry::class)->all()))],
            'name' => ['required', 'string', 'max:100'],
            'auth_type' => ['required', Rule::enum(AuthType::class)],
            'credentials' => ['nullable', 'array'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'transport' => ['required', Rule::enum(TransportKind::class)],
            'transport_config' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
            'enabled' => ['required', 'boolean'],
        ];
    }
}
