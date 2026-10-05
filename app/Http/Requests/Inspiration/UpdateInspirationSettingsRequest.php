<?php

declare(strict_types=1);

namespace App\Http\Requests\Inspiration;

use App\Inspiration\Contracts\Source;
use App\Inspiration\InspirationSettings;
use App\Inspiration\SourceManager;
use App\Inspiration\Support\CredentialFields;
use App\Models\User;
use Closure;
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
            'enabled_sources.*' => [
                'string',
                Rule::in($this->sourceKeys()),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->tier3NeedsAcknowledgement($value)) {
                        $fail("La fuente {$value} es Tier 3 y requiere aceptar el aviso antes de activarla.");
                    }
                },
            ],
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
            ->filter(static fn (Source $source): bool => CredentialFields::has($source->key()))
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

    /**
     * A Tier 3 source may only be enabled once it is acknowledged, either in
     * this same patch or in the user's persisted settings (the UI acknowledges
     * with a separate request before enabling the switch).
     */
    private function tier3NeedsAcknowledgement(mixed $value): bool
    {
        if (! is_string($value) || ! in_array($value, $this->tier3Keys(), true)) {
            return false;
        }

        return ! in_array($value, $this->acknowledgedTier3(), true);
    }

    /**
     * Union of the persisted acknowledgements and the ones sent in this patch.
     *
     * @return array<int, string>
     */
    private function acknowledgedTier3(): array
    {
        $incoming = array_filter(
            (array) $this->input('acknowledged_tier3', []),
            static fn (mixed $key): bool => is_string($key),
        );

        $persisted = [];
        $user = $this->user();

        if ($user instanceof User) {
            $persisted = app(InspirationSettings::class)->for($user)->acknowledgedTier3;
        }

        return array_values(array_unique(array_merge($persisted, $incoming)));
    }
}
