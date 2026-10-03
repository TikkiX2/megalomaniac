<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum StudyType: string
{
    case Lab = 'lab';
    case Imaging = 'imaging';
    case Report = 'report';
    case Other = 'other';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
