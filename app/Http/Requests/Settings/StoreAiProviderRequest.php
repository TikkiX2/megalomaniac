<?php

namespace App\Http\Requests\Settings;

use App\Ai\Enums\AiProtocol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiProviderRequest extends FormRequest
{
    /**
     * Provider names are unique per user, so the rule scopes the lookup to the
     * authenticated user instead of the whole registry.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('ai_providers', 'name')->where('user_id', $this->user()->getKey()),
            ],
            'protocol' => ['required', Rule::enum(AiProtocol::class)],
            'url' => ['required', 'string', 'url:http,https', 'max:2048'],
            'key' => ['required', 'string', 'max:500'],
            'model' => ['required', 'string', 'max:100'],
            'embeddings_model' => ['nullable', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
