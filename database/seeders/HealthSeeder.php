<?php

namespace Database\Seeders;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\ProfessionalType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Database\Seeder;

class HealthSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $neuro = HealthProfessional::create([
            'user_id' => $user->id,
            'type' => ProfessionalType::Professional,
            'name' => 'Dra. López',
            'specialty' => 'Neurología',
            'is_active' => true,
        ]);

        HealthCondition::create([
            'user_id' => $user->id,
            'kind' => ConditionKind::Diagnosis,
            'name' => 'Miopatía en estudio',
            'status' => ConditionStatus::Suspected,
            'severity' => Severity::Moderate,
            'provider_id' => $neuro->id,
            'notes' => 'Pendiente electromiograma y control.',
        ]);

        $hypo = HealthCondition::create([
            'user_id' => $user->id,
            'kind' => ConditionKind::Diagnosis,
            'name' => 'Hipotiroidismo',
            'status' => ConditionStatus::Active,
            'provider_id' => $neuro->id,
        ]);

        $t4 = HealthMedication::create([
            'user_id' => $user->id,
            'name' => 'Levotiroxina',
            'dose_amount' => 50,
            'dose_unit' => 'mcg',
            'route' => 'oral',
            'frequency_text' => '1 por día',
            'condition_id' => $hypo->id,
            'prescriber_id' => $neuro->id,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 5] as $daysAgo) {
            HealthMedicationIntake::create([
                'user_id' => $user->id,
                'medication_id' => $t4->id,
                'taken_at' => now()->subDays($daysAgo)->setTime(8, 0),
                'status' => IntakeStatus::Taken,
            ]);
        }

        foreach ([0, 3, 7, 14] as $daysAgo) {
            HealthMeasurement::create([
                'user_id' => $user->id,
                'type' => MeasurementType::Weight,
                'value' => 62.5 - ($daysAgo * 0.1),
                'unit' => 'kg',
                'measured_at' => now()->subDays($daysAgo),
            ]);
        }

        HealthMeasurement::create([
            'user_id' => $user->id,
            'type' => MeasurementType::BloodPressure,
            'value' => 118,
            'secondary_value' => 76,
            'unit' => 'mmHg',
            'measured_at' => now()->subDays(5),
        ]);

        HealthSymptom::create([
            'user_id' => $user->id,
            'symptom' => 'calambre en mano izquierda',
            'severity' => Severity::Mild,
            'occurred_at' => now()->subDays(2),
        ]);
    }
}
