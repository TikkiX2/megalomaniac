<?php

namespace App\Integrations\OAuth\Presets;

use App\Integrations\OAuth\OAuthPreset;
use App\Integrations\OAuth\OAuthToken;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RedditOAuthPreset implements OAuthPreset
{
    public function authorizeUrl(Connection $connection, string $state, string $codeVerifier): string
    {
        $credentials = $connection->credentials ?? [];

        return 'https://www.reddit.com/api/v1/authorize?'.http_build_query([
            'client_id' => (string) ($credentials['client_id'] ?? ''),
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $this->redirectUri($connection),
            'duration' => 'permanent',
            'scope' => implode(' ', ['read', 'identity', 'save', 'submit', 'vote']),
        ]);
    }

    public function exchange(Connection $connection, string $code, string $codeVerifier): OAuthToken
    {
        return $this->tokenRequest($connection, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($connection),
        ]);
    }

    public function refresh(Connection $connection, OAuthToken $token): OAuthToken
    {
        $refreshed = $this->tokenRequest($connection, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refreshToken,
        ]);

        return new OAuthToken(
            accessToken: $refreshed->accessToken,
            refreshToken: $refreshed->refreshToken ?? $token->refreshToken,
            expiresAt: $refreshed->expiresAt,
            scopes: $refreshed->scopes !== [] ? $refreshed->scopes : $token->scopes,
        );
    }

    protected function redirectUri(Connection $connection): string
    {
        return route('integrations.oauth.callback', $connection);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenRequest(Connection $connection, array $payload): OAuthToken
    {
        $credentials = $connection->credentials ?? [];

        $response = Http::asForm()
            ->withBasicAuth(
                (string) ($credentials['client_id'] ?? ''),
                (string) ($credentials['client_secret'] ?? ''),
            )
            ->withHeaders(['User-Agent' => 'megalomaniac/1.0'])
            ->post('https://www.reddit.com/api/v1/access_token', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Reddit token request failed with HTTP '.$response->status());
        }

        $data = $response->json();

        return new OAuthToken(
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])->toIso8601String()
                : now()->addHour()->toIso8601String(),
        );
    }
}
