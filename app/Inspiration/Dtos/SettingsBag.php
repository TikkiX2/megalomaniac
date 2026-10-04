<?php

declare(strict_types=1);

namespace App\Inspiration\Dtos;

/**
 * Typed view over the per-user inspiration settings body.
 */
class SettingsBag
{
    /**
     * @param  array<int, string>  $enabledSources
     * @param  array<string, array<string, string|null>>  $keys  Per-source credential-field map,
     *                                                           e.g. `['flickr' => ['key' => 'abc']]`. A flat string is normalized to `['key' => $string]`.
     * @param  array<int, string>  $acknowledgedTier3
     */
    public function __construct(
        public readonly array $enabledSources = [],
        public readonly array $keys = [],
        public readonly bool $maturity = false,
        public readonly ?string $zerochanUa = null,
        public readonly array $acknowledgedTier3 = [],
    ) {}

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body): self
    {
        return new self(
            enabledSources: array_values((array) ($body['enabled_sources'] ?? [])),
            keys: self::normalizeKeys((array) ($body['keys'] ?? [])),
            maturity: (bool) ($body['maturity'] ?? false),
            zerochanUa: isset($body['zerochan_ua']) ? (string) $body['zerochan_ua'] : null,
            acknowledgedTier3: array_values((array) ($body['acknowledged_tier3'] ?? [])),
        );
    }

    /**
     * Leniently coerce the persisted keys map into the canonical nested shape.
     *
     * The canonical shape is `{source: {field: value}}` (e.g.
     * `['pixiv' => ['refresh_token' => '…']]`). Legacy fixtures that stored a
     * flat `{source: string}` are wrapped as `['key' => $string]` so no stored
     * settings break after this change.
     *
     * @param  array<array-key, mixed>  $keys
     * @return array<string, array<string, string|null>>
     */
    private static function normalizeKeys(array $keys): array
    {
        $normalized = [];

        foreach ($keys as $source => $credentials) {
            if (! is_string($source)) {
                continue;
            }

            if (is_array($credentials)) {
                $normalized[$source] = array_map(
                    static fn (mixed $value): ?string => $value === null ? null : (string) $value,
                    $credentials,
                );

                continue;
            }

            if (is_scalar($credentials)) {
                $normalized[$source] = ['key' => (string) $credentials];
            }
        }

        return $normalized;
    }

    /**
     * @return array{enabled_sources: array<int, string>, keys: array<string, array<string, string|null>>, maturity: bool, zerochan_ua: ?string, acknowledged_tier3: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'enabled_sources' => $this->enabledSources,
            'keys' => $this->keys,
            'maturity' => $this->maturity,
            'zerochan_ua' => $this->zerochanUa,
            'acknowledged_tier3' => $this->acknowledgedTier3,
        ];
    }

    /**
     * A source counts as keyed when any of its credential fields is non-empty.
     */
    public function hasKey(string $source): bool
    {
        foreach ($this->keys[$source] ?? [] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    public function isEnabled(string $source): bool
    {
        return in_array($source, $this->enabledSources, true);
    }
}
