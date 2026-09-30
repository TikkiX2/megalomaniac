<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum MeasurementType: string
{
    case Weight = 'weight';
    case BloodPressure = 'blood_pressure';
    case HeartRate = 'heart_rate';
    case Glucose = 'glucose';
    case Temperature = 'temperature';
    case OxygenSaturation = 'oxygen_saturation';
    case Waist = 'waist';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
