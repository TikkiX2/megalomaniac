<?php

namespace App\Integrations\Enums;

enum ActionAccess: string
{
    case Read = 'read';
    case Write = 'write';
    case Destructive = 'destructive';

    public function requiresApproval(): bool
    {
        return $this !== self::Read;
    }
}
