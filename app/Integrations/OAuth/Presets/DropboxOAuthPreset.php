<?php

namespace App\Integrations\OAuth\Presets;

use App\Integrations\OAuth\OAuthPreset;
use App\Integrations\OAuth\OAuthToken;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DropboxOAuthPreset implements OAuthPreset
{
    public function authorizeUrl(Connection $connection, string $state, string $codeVerifier): string
    {
        return 'https://www.dropbox.com/oauth2/authorize?'.http_build_query([
            'client_id' => (string) config('services.dropbox.oauth.client_id'),
            'response_type' => 'code',
            'token_access_type' => 'offline',
            'redirect_uri' => $this->redirectUri($connection),
            'state' => $state,
        ]);
    }

    public function exchange(Connection $connection, string $code, string $codeVerifier): OAuthToken
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($connection),
        ]);
    }

    public function refresh(Connection $connection, OAuthToken $token): OAuthToken
    {
        $refreshed = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refreshToken,
        ]);

        return new OAuthToken(
            accessToken: $refreshed->accessToken,
            refreshToken: $refreshed->refreshToken ?? $token->refreshToken,
            expiresAt: $refreshed->expiresAt,
        );
    }

    protected function redirectUri(Connection $connection): string
    {
        return route('integrations.oauth.callback', $connection);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenRequest(array $payload): OAuthToken
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('services.dropbox.oauth.client_id'),
                (string) config('services.dropbox.oauth.client_secret'),
            )
            ->post('https://api.dropboxapi.com/oauth2/token', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Dropbox token request failed with HTTP '.$response->status());
        }

        $data = $response->json();

        return new OAuthToken(
            accessToken: (string) ($data['access_token'] ?? ''),
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])->toIso8601String()
                : null,
        );
    }
}
