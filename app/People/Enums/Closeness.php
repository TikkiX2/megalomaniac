<?php

declare(strict_types=1);

namespace App\People\Enums;

enum Closeness: string
{
    case InnerCircle = 'inner_circle';
    case Close = 'close';
    case Friend = 'friend';
    case Acquaintance = 'acquaintance';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
