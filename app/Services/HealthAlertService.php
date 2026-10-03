<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HealthAlert;
use App\Models\HealthSymptom;
use Illuminate\Support\Carbon;

class HealthAlertService
{
    public function monitorSymptom(HealthSymptom $symptom): void
    {
        if ($symptom->severity === 'severe') {
            HealthAlert::create([
                'user_id' => $symptom->user_id,
                'type' => 'symptom',
                'severity' => 'severe',
                'message' => "Severe symptom logged: {$symptom->symptom}",
                'triggered_at' => Carbon::now(),
            ]);
        }
    }
}
