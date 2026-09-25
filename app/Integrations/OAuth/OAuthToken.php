<?php

namespace App\Integrations\OAuth;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class OAuthToken
{
    /**
     * @param  string[]  $scopes
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?string $expiresAt = null,
        public array $scopes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: $data['expires_at'] ?? null,
            scopes: $data['scopes'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt,
            'scopes' => $this->scopes,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function isExpired(int $skewSeconds = 60): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return now()->addSeconds($skewSeconds)->greaterThanOrEqualTo(
            CarbonImmutable::parse($this->expiresAt),
        );
    }

    public function expiresAt(): ?CarbonInterface
    {
        return $this->expiresAt ? CarbonImmutable::parse($this->expiresAt) : null;
    }
}
