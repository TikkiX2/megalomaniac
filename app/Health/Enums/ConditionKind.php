<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum ConditionKind: string
{
    case Condition = 'condition';
    case Diagnosis = 'diagnosis';
    case Allergy = 'allergy';
    case Surgery = 'surgery';
    case FamilyHistory = 'family_history';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
