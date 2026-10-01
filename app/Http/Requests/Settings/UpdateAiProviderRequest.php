<?php

namespace App\Http\Requests\Settings;

use App\Ai\Enums\AiProtocol;
use App\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiProviderRequest extends FormRequest
{
    /**
     * Every field is optional so the settings sheet can PATCH a single column
     * (the enabled checkbox, the model, ...). `key` is nullable on purpose: a
     * blank value means "keep the stored key" and is stripped in the controller.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:80',
                Rule::unique('ai_providers', 'name')
                    ->where('user_id', $this->user()->getKey())
                    ->ignore($this->providerId()),
            ],
            'protocol' => ['sometimes', 'required', Rule::enum(AiProtocol::class)],
            'url' => ['sometimes', 'required', 'string', 'url:http,https', 'max:2048'],
            'key' => ['sometimes', 'nullable', 'string', 'max:500'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'embeddings_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** True when the checkbox was sent at all, so its value can be coerced. */
    public function hasEnabled(): bool
    {
        return $this->has('enabled');
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    /**
     * Id of the provider being edited, ignored by the unique name rule.
     */
    private function providerId(): int
    {
        $provider = $this->route('provider');

        return $provider instanceof AiProvider ? $provider->getKey() : (int) $provider;
    }
}
