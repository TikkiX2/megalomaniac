<?php

use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\Person;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(HealthService::class);
    $this->user = User::factory()->create();
});

it('creates and updates conditions scoped to the user', function () {
    $condition = $this->service->createCondition($this->user, [
        'kind' => 'diagnosis',
        'name' => 'Hipotiroidismo',
        'status' => 'active',
    ]);

    expect($condition->user_id)->toBe($this->user->id);

    $updated = $this->service->updateCondition($this->user, $condition, ['status' => 'resolved']);

    expect($updated->status->value)->toBe('resolved');
});

it('refuses to touch another user records', function () {
    $intruder = User::factory()->create();
    $condition = HealthCondition::factory()->create();

    $this->expectException(ModelNotFoundException::class);
    $this->expectExceptionMessage('HealthCondition not found.');

    $this->service->findCondition($intruder, $condition->id);
});

it('logs intakes through the medication and deletes them', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $intake = $this->service->logIntake($this->user, $medication, [
        'taken_at' => now()->subHour()->toDateTimeString(),
        'status' => IntakeStatus::Taken->value,
    ]);

    expect($medication->intakes()->count())->toBe(1);

    $this->service->deleteIntake($this->user, $intake);

    expect($medication->intakes()->count())->toBe(0);
});

it('syncs the profile weight when logging, updating and deleting a weight measurement', function () {
    $first = $this->service->logMeasurement($this->user, [
        'type' => MeasurementType::Weight->value,
        'value' => 61.5,
        'unit' => 'kg',
        'measured_at' => now()->subDays(10)->toDateTimeString(),
    ]);
    $latest = $this->service->logMeasurement($this->user, [
        'type' => MeasurementType::Weight->value,
        'value' => 62.5,
        'unit' => 'kg',
        'measured_at' => now()->subDay()->toDateTimeString(),
    ]);

    expect((float) $this->user->fresh()->weight)->toBe(62.5);

    $this->service->deleteMeasurement($this->user, $latest);

    expect((float) $this->user->fresh()->weight)->toBe(61.5);

    $this->service->deleteMeasurement($this->user, $first);

    expect($this->user->fresh()->weight)->toBeNull();
});

it('does not sync weight for family members', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $this->user->forceFill(['weight' => 62.0])->save();

    $this->service->logMeasurement($this->user, [
        'person_id' => $person->id,
        'type' => MeasurementType::Weight->value,
        'value' => 70.0,
        'unit' => 'kg',
        'measured_at' => now()->toDateTimeString(),
    ]);

    expect((float) $this->user->fresh()->weight)->toBe(62.0);
});

it('logs symptoms and builds a summary', function () {
    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);
    $this->service->logSymptom($this->user, [
        'symptom' => 'calambre',
        'severity' => Severity::Moderate->value,
        'occurred_at' => now()->toDateTimeString(),
    ]);

    $summary = $this->service->summary($this->user);

    expect($summary)->toHaveKeys(['active_conditions', 'active_medications', 'last_measurements', 'recent_symptoms'])
        ->and($summary['recent_symptoms'])->toHaveCount(1);
});
