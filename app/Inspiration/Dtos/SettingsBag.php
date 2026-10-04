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
     * @param  array<string, string>  $keys
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
            keys: (array) ($body['keys'] ?? []),
            maturity: (bool) ($body['maturity'] ?? false),
            zerochanUa: isset($body['zerochan_ua']) ? (string) $body['zerochan_ua'] : null,
            acknowledgedTier3: array_values((array) ($body['acknowledged_tier3'] ?? [])),
        );
    }

    /**
     * @return array{enabled_sources: array<int, string>, keys: array<string, string>, maturity: bool, zerochan_ua: ?string, acknowledged_tier3: array<int, string>}
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

    public function hasKey(string $source): bool
    {
        return isset($this->keys[$source]) && $this->keys[$source] !== '';
    }

    public function isEnabled(string $source): bool
    {
        return in_array($source, $this->enabledSources, true);
    }
}
