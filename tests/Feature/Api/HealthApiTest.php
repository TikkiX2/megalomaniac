<?php

use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->headers = ['Authorization' => 'Bearer '.$this->user->createToken('api-token')->plainTextToken];
});

it('creates, lists and updates conditions scoped to the token user', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Miopatía', 'status' => 'suspected']);
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/conditions?search=mio')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Miopatía');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/conditions', [
            'kind' => 'diagnosis',
            'name' => 'Hipotiroidismo',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Hipotiroidismo');

    $condition = HealthCondition::where('name', 'Hipotiroidismo')->firstOrFail();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/conditions/{$condition->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/conditions', ['name' => 'Incompleta'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kind', 'status']);
});

it('logs medications, intakes, measurements and symptoms', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/medications', ['name' => 'Levotiroxina', 'is_active' => true])
        ->assertCreated();

    $medication = HealthMedication::where('user_id', $this->user->id)->firstOrFail();

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/health/medications/{$medication->id}/intakes", [
            'taken_at' => now()->toDateTimeString(),
            'status' => 'taken',
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/measurements', [
            'type' => 'weight',
            'value' => 62.5,
            'unit' => 'kg',
            'measured_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    expect((float) $this->user->fresh()->weight)->toBe(62.5);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/symptoms', [
            'symptom' => 'calambre',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.severity', 'mild');
});

it('protects ownership and exposes the summary', function () {
    $foreign = HealthCondition::factory()->create();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/conditions/{$foreign->id}", ['status' => 'resolved'])
        ->assertForbidden();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/conditions/{$foreign->id}")
        ->assertForbidden();

    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/summary')
        ->assertOk()
        ->assertJsonStructure(['data' => ['active_conditions', 'active_medications', 'last_measurements', 'recent_symptoms']]);
});

it('creates, updates, shows and scopes professionals', function () {
    $professional = HealthProfessional::factory()->create(['user_id' => $this->user->id, 'name' => 'Dra. Gómez']);
    HealthProfessional::factory()->create(['name' => 'Ajeno']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/professionals?search=Dra')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dra. Gómez');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/professionals', ['type' => 'center', 'name' => 'Clínica Sur'])
        ->assertCreated()
        ->assertJsonPath('data.type', 'center');

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/professionals/{$professional->id}", ['specialty' => 'Neurología'])
        ->assertOk()
        ->assertJsonPath('data.specialty', 'Neurología');

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/health/professionals/{$professional->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Dra. Gómez');

    $foreign = HealthProfessional::factory()->create();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/professionals/{$foreign->id}")
        ->assertForbidden();
});

it('lists and destroys medications and intakes with ownership checks', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id, 'name' => 'Levotiroxina']);
    HealthMedication::factory()->create(['name' => 'Levotiroxina ajena']);
    $intake = HealthMedicationIntake::factory()->create(['user_id' => $this->user->id, 'medication_id' => $medication->id]);
    $foreignMedication = HealthMedication::factory()->create();

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/medications?search=levo')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Levotiroxina');

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/health/medications/{$medication->id}/intakes")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'taken');

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/health/medications/{$foreignMedication->id}/intakes")
        ->assertForbidden();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/medications/{$medication->id}/intakes/{$intake->id}")
        ->assertOk();

    expect(HealthMedicationIntake::find($intake->id))->toBeNull();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/medications/{$medication->id}")
        ->assertOk();

    expect(HealthMedication::find($medication->id))->toBeNull();
});

it('lists and destroys measurements and symptoms with filters', function () {
    $measurement = HealthMeasurement::factory()->create(['user_id' => $this->user->id, 'type' => 'heart_rate']);
    HealthMeasurement::factory()->create(['user_id' => $this->user->id, 'type' => 'weight']);
    HealthMeasurement::factory()->create(['type' => 'heart_rate']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/measurements?type=heart_rate')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'heart_rate');

    $symptom = HealthSymptom::factory()->create(['user_id' => $this->user->id, 'symptom' => 'mareo', 'severity' => 'severe']);
    HealthSymptom::factory()->create(['symptom' => 'mareo']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/symptoms?search=mareo&severity=severe')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.severity', 'severe');

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/measurements/{$measurement->id}")
        ->assertOk();

    expect(HealthMeasurement::find($measurement->id))->toBeNull();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/symptoms/{$symptom->id}")
        ->assertOk();

    expect(HealthSymptom::find($symptom->id))->toBeNull();
});

it('normalises offset timestamps to utc on store and update', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/measurements', [
            'type' => 'weight',
            'value' => 70,
            'unit' => 'kg',
            'measured_at' => '2026-09-30T12:00:00-03:00',
        ])
        ->assertCreated();

    $measurement = HealthMeasurement::where('user_id', $this->user->id)->firstOrFail();

    expect($measurement->measured_at->utc()->toDateTimeString())->toBe('2026-09-30 15:00:00');

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/measurements/{$measurement->id}", [
            'measured_at' => '2026-09-30T10:00:00-03:00',
        ])
        ->assertOk();

    expect($measurement->fresh()->measured_at->utc()->toDateTimeString())->toBe('2026-09-30 13:00:00');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/symptoms', [
            'symptom' => 'mareo',
            'severity' => 'mild',
            'occurred_at' => '2026-09-30T12:00:00-03:00',
        ])
        ->assertCreated();

    $symptom = HealthSymptom::where('user_id', $this->user->id)->firstOrFail();

    expect($symptom->occurred_at->utc()->toDateTimeString())->toBe('2026-09-30 15:00:00');

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/symptoms/{$symptom->id}", [
            'occurred_at' => '2026-09-30T10:00:00-03:00',
        ])
        ->assertOk();

    expect($symptom->fresh()->occurred_at->utc()->toDateTimeString())->toBe('2026-09-30 13:00:00');

    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/health/medications/{$medication->id}/intakes", [
            'taken_at' => '2026-09-30T12:00:00-03:00',
            'status' => 'taken',
        ])
        ->assertCreated();

    $intake = HealthMedicationIntake::where('medication_id', $medication->id)->firstOrFail();

    expect($intake->taken_at->utc()->toDateTimeString())->toBe('2026-09-30 15:00:00');
});

it('allows partial updates without the store-required fields', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/medications/{$medication->id}", ['name' => 'Ibuprofeno'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ibuprofeno');

    $measurement = HealthMeasurement::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/measurements/{$measurement->id}", ['value' => 71.5])
        ->assertOk()
        ->assertJsonPath('data.value', '71.50');

    $symptom = HealthSymptom::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/symptoms/{$symptom->id}", ['severity' => 'moderate'])
        ->assertOk()
        ->assertJsonPath('data.severity', 'moderate');

    $professional = HealthProfessional::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/professionals/{$professional->id}", ['name' => 'Dra. Actualizada'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Dra. Actualizada');
});
