<?php

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates health records with enum casts and defaults', function () {
    $user = User::factory()->create();
    $condition = HealthCondition::factory()->create([
        'user_id' => $user->id,
        'kind' => ConditionKind::Diagnosis,
        'status' => ConditionStatus::Active,
        'severity' => Severity::Moderate,
    ]);

    expect($condition->fresh()->kind)->toBe(ConditionKind::Diagnosis)
        ->and($condition->fresh()->status)->toBe(ConditionStatus::Active)
        ->and($condition->fresh()->severity)->toBe(Severity::Moderate)
        ->and($condition->user->is($user))->toBeTrue();
});

it('links records to a family person optionally', function () {
    $person = Person::factory()->create();
    $measurement = HealthMeasurement::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'type' => MeasurementType::BloodPressure,
        'value' => 120,
        'secondary_value' => 80,
        'unit' => 'mmHg',
    ]);

    expect($measurement->person->is($person))->toBeTrue();
});

it('relates medications, intakes, providers and symptoms', function () {
    $user = User::factory()->create();
    $provider = HealthProfessional::factory()->create(['user_id' => $user->id]);
    $medication = HealthMedication::factory()->create([
        'user_id' => $user->id,
        'prescriber_id' => $provider->id,
        'is_active' => true,
    ]);
    $intake = HealthMedicationIntake::factory()->create([
        'user_id' => $user->id,
        'medication_id' => $medication->id,
    ]);
    $symptom = HealthSymptom::factory()->create(['user_id' => $user->id]);

    expect($medication->prescriber->is($provider))->toBeTrue()
        ->and($medication->intakes()->count())->toBe(1)
        ->and($intake->medication->is($medication))->toBeTrue()
        ->and($symptom->severity)->toBeInstanceOf(Severity::class);
});
