<?php

use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('logs a weight measurement and syncs the profile weight', function () {
    $this->post('/health/measurements', [
        'type' => 'weight',
        'value' => 62.5,
        'unit' => 'kg',
        'measured_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    expect(HealthMeasurement::where('user_id', $this->user->id)->count())->toBe(1)
        ->and((float) $this->user->fresh()->weight)->toBe(62.5);
});

it('logs a blood pressure measurement with secondary value', function () {
    $this->post('/health/measurements', [
        'type' => 'blood_pressure',
        'value' => 118,
        'secondary_value' => 76,
        'unit' => 'mmHg',
        'measured_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    $measurement = HealthMeasurement::firstOrFail();

    expect($measurement->secondary_value)->not->toBeNull();
});

it('renders measurements with chart data and symptoms list', function () {
    HealthMeasurement::factory()->count(2)->create(['user_id' => $this->user->id]);
    HealthSymptom::factory()->create(['user_id' => $this->user->id, 'symptom' => 'calambre']);

    $this->get('/health/measurements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/measurements/Index')
            ->has('typeOptions')
            ->has('unitSuggestions')
            ->has('chart', 2));

    $this->get('/health/symptoms')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/symptoms/Index')
            ->has('severityOptions')
            ->where('symptoms.data', fn ($rows) => collect($rows)->pluck('symptom')->contains('calambre')));
});

it('forbids deleting another user measurement or symptom', function () {
    $measurement = HealthMeasurement::factory()->create();
    $symptom = HealthSymptom::factory()->create();

    $this->delete("/health/measurements/{$measurement->id}")->assertForbidden();
    $this->delete("/health/symptoms/{$symptom->id}")->assertForbidden();
});

it('scopes the measurements list and chart to the authenticated user personal rows', function () {
    HealthMeasurement::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'weight',
        'value' => 70,
        'measured_at' => now()->subDay(),
    ]);
    HealthMeasurement::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => Person::factory()->create(['user_id' => $this->user->id])->id,
        'type' => 'weight',
        'value' => 80,
        'measured_at' => now()->subDays(2),
    ]);
    HealthMeasurement::factory()->create([
        'type' => 'weight',
        'value' => 90,
        'measured_at' => now(),
    ]);

    $this->get('/health/measurements?type=weight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('measurements.data', fn ($rows) => collect($rows)->pluck('value')->all() === ['70.00', '80.00'])
            ->where('chart', fn ($rows) => collect($rows)->pluck('value')->map(fn ($value) => (float) $value)->all() === [70.0]));
});

it('stores measurement timestamps as utc instants from offset input', function () {
    $this->post('/health/measurements', [
        'type' => 'weight',
        'value' => 70,
        'unit' => 'kg',
        'measured_at' => '2026-09-30T12:00:00-03:00',
    ])->assertRedirect();

    $measurement = HealthMeasurement::where('user_id', $this->user->id)->firstOrFail();

    expect($measurement->measured_at->utc()->toDateTimeString())->toBe('2026-09-30 15:00:00');
});

it('defaults symptom severity to mild when it is omitted', function () {
    $this->post('/health/symptoms', [
        'symptom' => 'mareo',
        'occurred_at' => now()->toDateTimeString(),
    ])->assertRedirect(route('health.symptoms.index'));

    expect(HealthSymptom::where('user_id', $this->user->id)->firstOrFail()->severity->value)->toBe('mild');
});

it('stores symptom timestamps as utc instants from offset input', function () {
    $this->post('/health/symptoms', [
        'symptom' => 'mareo',
        'severity' => 'mild',
        'occurred_at' => '2026-09-30T12:00:00-03:00',
    ])->assertRedirect();

    $symptom = HealthSymptom::where('user_id', $this->user->id)->firstOrFail();

    expect($symptom->occurred_at->utc()->toDateTimeString())->toBe('2026-09-30 15:00:00');
});

it('charts the selected measurement type', function () {
    HealthMeasurement::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'weight',
        'value' => 70,
        'measured_at' => now()->subDay(),
    ]);
    HealthMeasurement::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'heart_rate',
        'value' => 72,
        'unit' => 'bpm',
    ]);

    $this->get('/health/measurements?type=heart_rate')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('chart', fn ($rows) => collect($rows)->pluck('value')->map(fn ($value) => (float) $value)->all() === [72.0]));
});

it('ignores an unknown measurement type filter', function () {
    HealthMeasurement::factory()->create(['user_id' => $this->user->id, 'type' => 'weight', 'value' => 70]);

    $this->get('/health/measurements?type=bogus')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('measurements.data', fn ($rows) => collect($rows)->pluck('value')->all() === ['70.00'])
            ->where('chart', fn ($rows) => collect($rows)->pluck('value')->map(fn ($value) => (float) $value)->all() === [70.0]));
});

it('creates updates and deletes a measurement from the web', function () {
    $this->post('/health/measurements', [
        'type' => 'heart_rate',
        'value' => 72,
        'unit' => 'bpm',
        'measured_at' => now()->toDateTimeString(),
    ])->assertRedirect(route('health.measurements.index'));

    $measurement = HealthMeasurement::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/measurements/{$measurement->id}", ['value' => 75])
        ->assertRedirect(route('health.measurements.index'));

    expect((float) $measurement->fresh()->value)->toBe(75.0)
        ->and($measurement->fresh()->unit)->toBe('bpm');

    $this->delete("/health/measurements/{$measurement->id}")->assertRedirect(route('health.measurements.index'));

    expect(HealthMeasurement::find($measurement->id))->toBeNull();
});

it('filters symptoms by search and severity for the authenticated user', function () {
    HealthSymptom::factory()->create([
        'user_id' => $this->user->id,
        'symptom' => 'calambre nocturno',
        'severity' => 'severe',
        'occurred_at' => now(),
    ]);
    HealthSymptom::factory()->create([
        'user_id' => $this->user->id,
        'symptom' => 'calambre',
        'severity' => 'mild',
        'occurred_at' => now()->subDay(),
    ]);
    HealthSymptom::factory()->create([
        'symptom' => 'calambre',
        'severity' => 'severe',
        'occurred_at' => now()->subDays(2),
    ]);

    $this->get('/health/symptoms?search=calambre&severity=severe')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('symptoms.data', fn ($rows) => collect($rows)->pluck('symptom')->all() === ['calambre nocturno']));
});

it('creates updates and deletes a symptom from the web', function () {
    $this->post('/health/symptoms', [
        'symptom' => 'mareo',
        'severity' => 'moderate',
        'occurred_at' => now()->toDateTimeString(),
    ])->assertRedirect(route('health.symptoms.index'));

    $symptom = HealthSymptom::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/symptoms/{$symptom->id}", ['severity' => 'severe'])
        ->assertRedirect(route('health.symptoms.index'));

    expect($symptom->fresh()->severity->value)->toBe('severe')
        ->and($symptom->fresh()->symptom)->toBe('mareo');

    $this->delete("/health/symptoms/{$symptom->id}")->assertRedirect(route('health.symptoms.index'));

    expect(HealthSymptom::find($symptom->id))->toBeNull();
});
