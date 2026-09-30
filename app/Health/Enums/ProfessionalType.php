<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum ProfessionalType: string
{
    case Professional = 'professional';
    case Center = 'center';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
