<?php

namespace App\Integrations\OAuth;

use App\Models\Connection;

interface OAuthPreset
{
    public function authorizeUrl(Connection $connection, string $state, string $codeVerifier): string;

    public function exchange(Connection $connection, string $code, string $codeVerifier): OAuthToken;

    public function refresh(Connection $connection, OAuthToken $token): OAuthToken;
}
