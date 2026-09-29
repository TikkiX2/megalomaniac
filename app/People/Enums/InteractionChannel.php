<?php

declare(strict_types=1);

namespace App\People\Enums;

enum InteractionChannel: string
{
    case InPerson = 'in_person';
    case Call = 'call';
    case Video = 'video';
    case Message = 'message';
    case Email = 'email';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
