<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Models\HealthAlert;
use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\User;

class HealthAlertService
{
    public static function checkForAlerts(User $user): void
    {
        $severeSymptoms = HealthSymptom::where('user_id', $user->id)
            ->where('severity', 'severe')
            ->get();

        foreach ($severeSymptoms as $symptom) {
            $exists = HealthAlert::where('user_id', $user->id)
                ->where('type', 'symptom_severe')
                ->where('related_id', $symptom->id)
                ->where('related_type', HealthSymptom::class)
                ->exists();

            if (! $exists) {
                self::createAlert($user, 'symptom_severe', $symptom->id, HealthSymptom::class,
                    "Síntoma severo detectado: {$symptom->symptom}");
            }
        }

        $extremeMeasurements = HealthMeasurement::where('user_id', $user->id)
            ->whereNotNull('flag')
            ->whereIn('flag', ['high', 'low'])
            ->get();

        foreach ($extremeMeasurements as $measurement) {
            $type = $measurement->flag === 'high' ? 'study_result_high' : 'study_result_low';
            $description = $measurement->flag === 'high' ? 'Valor alto' : 'Valor bajo';

            $exists = HealthAlert::where('user_id', $user->id)
                ->where('type', $type)
                ->where('related_id', $measurement->id)
                ->where('related_type', HealthMeasurement::class)
                ->exists();

            if (! $exists) {
                self::createAlert($user, $type, $measurement->id, HealthMeasurement::class,
                    "{$description} de medición detectado: {$measurement->type->value} = {$measurement->value} {$measurement->unit}");
            }
        }
    }

    private static function createAlert(User $user, string $type, int $relatedId, string $relatedType, string $message): HealthAlert
    {
        return HealthAlert::create([
            'user_id' => $user->id,
            'type' => $type,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
            'message' => $message,
        ]);
    }

    public static function getUnreadAlerts(User $user)
    {
        return HealthAlert::where('user_id', $user->id)
            ->where('is_read', false)
            ->orderByDesc('triggered_at')
            ->get();
    }

    public static function markAsRead(User $user, int $alertId): void
    {
        $alert = HealthAlert::where('user_id', $user->id)
            ->where('id', $alertId)
            ->first();

        if ($alert) {
            $alert->update(['is_read' => true]);
        }
    }
}
