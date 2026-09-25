<?php

namespace App\Integrations\OAuth\Presets;

use App\Integrations\OAuth\OAuthPreset;
use App\Integrations\OAuth\OAuthToken;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuthPreset implements OAuthPreset
{
    public function authorizeUrl(Connection $connection, string $state, string $codeVerifier): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri($connection),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'code_challenge' => $this->codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ]);
    }

    public function exchange(Connection $connection, string $code, string $codeVerifier): OAuthToken
    {
        return $this->tokenRequest([
            'code' => $code,
            'code_verifier' => $codeVerifier,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri($connection),
        ]);
    }

    public function refresh(Connection $connection, OAuthToken $token): OAuthToken
    {
        $refreshed = $this->tokenRequest([
            'refresh_token' => $token->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        return new OAuthToken(
            accessToken: $refreshed->accessToken,
            refreshToken: $refreshed->refreshToken ?? $token->refreshToken,
            expiresAt: $refreshed->expiresAt,
            scopes: $refreshed->scopes !== [] ? $refreshed->scopes : $token->scopes,
        );
    }

    /**
     * @return string[]
     */
    public function scopes(): array
    {
        return [
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/calendar.events',
        ];
    }

    protected function clientId(): string
    {
        return (string) config('services.google.oauth.client_id');
    }

    protected function clientSecret(): string
    {
        return (string) config('services.google.oauth.client_secret');
    }

    protected function redirectUri(Connection $connection): string
    {
        return route('integrations.oauth.callback', $connection);
    }

    protected function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function tokenRequest(array $payload): OAuthToken
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', $payload + [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('OAuth token request failed with HTTP '.$response->status());
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
