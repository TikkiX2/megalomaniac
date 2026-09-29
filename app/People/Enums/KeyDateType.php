<?php

declare(strict_types=1);

namespace App\People\Enums;

enum KeyDateType: string
{
    case Birthday = 'birthday';
    case Anniversary = 'anniversary';
    case Graduation = 'graduation';
    case Memorial = 'memorial';
    case Custom = 'custom';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
