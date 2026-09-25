<?php

namespace App\Integrations\OAuth;

use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\OAuth\Presets\GoogleOAuthPreset;
use App\Models\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OAuthBroker
{
    public function redirectUrl(Connection $connection): string
    {
        $state = Str::random(40);
        $verifier = Str::random(64);

        Cache::put("oauth:state:{$state}", [
            'connection_id' => $connection->id,
            'verifier' => $verifier,
        ], now()->addMinutes(10));

        return $this->presetFor($connection)->authorizeUrl($connection, $state, $verifier);
    }

    public function handleCallback(Connection $connection, string $code, string $state): OAuthToken
    {
        $payload = Cache::pull("oauth:state:{$state}");

        if (! is_array($payload) || (int) ($payload['connection_id'] ?? 0) !== $connection->id) {
            throw ValidationException::withMessages([
                'state' => 'Estado OAuth inválido o expirado.',
            ]);
        }

        $token = $this->presetFor($connection)->exchange(
            $connection,
            $code,
            (string) $payload['verifier'],
        );

        $connection->forceFill([
            'credentials' => array_merge($connection->credentials ?? [], $token->toArray()),
            'status' => ConnectionStatus::Ok,
            'status_message' => null,
        ])->save();

        return $token;
    }

    public function refreshIfNeeded(Connection $connection): Connection
    {
        $token = OAuthToken::fromArray($connection->credentials ?? []);

        if (! $token->isExpired(60) || ! $token->refreshToken) {
            return $connection;
        }

        $refreshed = $this->presetFor($connection)->refresh($connection, $token);

        $connection->forceFill([
            'credentials' => array_merge($connection->credentials ?? [], $refreshed->toArray()),
        ])->save();

        return $connection;
    }

    public function presetFor(Connection $connection): OAuthPreset
    {
        return match ($connection->kind) {
            'google' => app(GoogleOAuthPreset::class),
            default => throw new RuntimeException("No OAuth preset registered for [{$connection->kind}]."),
        };
    }
}
