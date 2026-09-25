<?php

namespace App\Integrations\Enums;

enum ConnectionStatus: string
{
    case Unknown = 'unknown';
    case Ok = 'ok';
    case Error = 'error';
    case Expired = 'expired';
}
