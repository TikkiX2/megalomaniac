<?php

declare(strict_types=1);

namespace App\People\Enums;

enum RelationshipStatus: string
{
    case Single = 'single';
    case Dating = 'dating';
    case InRelationship = 'in_relationship';
    case Engaged = 'engaged';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';
    case Complicated = 'complicated';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
