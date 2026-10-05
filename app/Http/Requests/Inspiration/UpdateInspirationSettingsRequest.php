<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspiration;

use App\Inspiration\Contracts\Source;
use App\Inspiration\SourceManager;
use App\Inspiration\Support\CredentialFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a partial settings patch for the inspiration module.
 *
 * Only registered sources whose capabilities require a key contribute
 * `keys.{source}` rules, and only for the credential fields declared in
 * `inspiration.credential_fields`. Sources without `needsKey` are ignored:
 * their `keys.*` payload is dropped by the controller, never persisted.
 */
class UpdateInspirationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'enabled_sources' => ['nullable', 'array'],
            'enabled_sources.*' => ['string', Rule::in($this->sourceKeys())],
            'keys' => ['nullable', 'array'],
            'maturity' => ['nullable', 'boolean'],
            'zerochan_ua' => ['nullable', 'string', 'max:255'],
            'acknowledged_tier3' => ['nullable', 'array'],
            'acknowledged_tier3.*' => ['string', Rule::in($this->tier3Keys())],
        ];

        foreach ($this->keySources() as $key => $source) {
            $fields = CredentialFields::for($key);

            $rules["keys.{$key}"] = ['nullable', 'array:'.implode(',', $fields)];

            foreach ($fields as $field) {
                $rules["keys.{$key}.{$field}"] = ['nullable', 'string', 'max:500'];
            }
        }

        return $rules;
    }

    /**
     * Every registered source key.
     *
     * @return array<int, string>
     */
    private function sourceKeys(): array
    {
        return app(SourceManager::class)->all()->keys()->all();
    }

    /**
     * Registered sources that require a credential.
     *
     * @return array<string, Source>
     */
    private function keySources(): array
    {
        return app(SourceManager::class)->all()
            ->filter(static fn (Source $source): bool => $source->capabilities()->needsKey)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function tier3Keys(): array
    {
        $keys = config('inspiration.tier3', []);

        return is_array($keys) ? array_values($keys) : [];
    }
}
