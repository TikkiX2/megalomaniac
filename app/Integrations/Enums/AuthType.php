<?php

namespace App\Integrations\Enums;

enum AuthType: string
{
    case ApiToken = 'api_token';
    case Basic = 'basic';
    case OAuth2 = 'oauth2';
    case None = 'none';
    case Qr = 'qr';
}
