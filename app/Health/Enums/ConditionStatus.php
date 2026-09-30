<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum ConditionStatus: string
{
    case Suspected = 'suspected';
    case Active = 'active';
    case Resolved = 'resolved';
    case InRemission = 'in_remission';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
