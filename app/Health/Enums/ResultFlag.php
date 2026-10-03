<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum ResultFlag: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Unknown = 'unknown';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
