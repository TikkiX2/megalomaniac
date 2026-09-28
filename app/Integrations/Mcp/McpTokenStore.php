<?php

namespace App\Integrations\Mcp;

use App\Integrations\Enums\ConnectionStatus;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Throwable;

class McpTokenStore
{
    public function __construct(
        private readonly McpClientFactory $factory,
    ) {}

    public function store(Connection $connection, TokenSet $token): Connection
    {
        $credentials = array_filter(array_merge($connection->credentials ?? [], [
            'access_token' => $token->accessToken,
            'refresh_token' => $token->refreshToken,
            'expires_at' => $token->expiresAt !== null
                ? CarbonImmutable::createFromTimestamp($token->expiresAt)->toIso8601String()
                : null,
            'token_type' => $token->tokenType,
            'scope' => $token->scope,
            'client_id' => $token->clientId,
            'client_secret' => $token->clientSecret,
        ]), fn (mixed $value): bool => $value !== null && $value !== '');

        $connection->forceFill([
            'credentials' => $credentials,
            'status' => ConnectionStatus::Ok,
            'status_message' => null,
        ])->save();

        return $connection;
    }

    public function ensureFresh(Connection $connection): Connection
    {
        $expiresAt = $connection->credentials['expires_at'] ?? null;

        if ($expiresAt === null || now()->addSeconds(60)->lt(CarbonImmutable::parse($expiresAt))) {
            return $connection;
        }

        $refreshToken = $connection->credentials['refresh_token'] ?? null;

        if ($refreshToken === null) {
            return $connection;
        }

        try {
            $client = $this->factory->base($connection)->withOAuth(
                $connection->credentials['client_id'] ?? null,
                $connection->credentials['client_secret'] ?? null,
                $connection->credentials['scope'] ?? null,
            );

            $token = $client->oAuthClient()->refreshCredentials(
                $refreshToken,
                $connection->credentials['client_id'] ?? null,
                $connection->credentials['client_secret'] ?? null,
            );
        } catch (Throwable $e) {
            $connection->forceFill([
                'status' => ConnectionStatus::Expired,
                'status_message' => 'No se pudo refrescar el token OAuth: '.$e->getMessage(),
            ])->save();

            return $connection;
        }

        return $this->store($connection, $token);
    }
}
