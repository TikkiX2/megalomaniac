<?php

declare(strict_types=1);

namespace App\People\Enums;

enum PreferredContactChannel: string
{
    case Whatsapp = 'whatsapp';
    case Phone = 'phone';
    case Email = 'email';
    case Message = 'message';
    case InPerson = 'in_person';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
