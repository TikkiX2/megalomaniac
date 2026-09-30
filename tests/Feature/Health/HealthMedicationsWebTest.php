<?php

use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates and updates a medication', function () {
    $this->post('/health/medications', [
        'name' => 'Levotiroxina',
        'dose_amount' => 50,
        'dose_unit' => 'mcg',
        'is_active' => true,
    ])->assertRedirect(route('health.medications.index'));

    $medication = HealthMedication::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/medications/{$medication->id}", ['is_active' => false])
        ->assertRedirect(route('health.medications.index'));

    expect($medication->fresh()->is_active)->toBeFalse();
});

it('logs and deletes intakes from the list', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $this->post("/health/medications/{$medication->id}/intakes", [
        'taken_at' => now()->toDateTimeString(),
        'status' => 'taken',
    ])->assertRedirect();

    $intake = HealthMedicationIntake::where('medication_id', $medication->id)->firstOrFail();

    $this->delete("/health/medications/{$medication->id}/intakes/{$intake->id}")->assertRedirect();

    expect(HealthMedicationIntake::find($intake->id))->toBeNull();
});

it('lists and filters medications of the authenticated user', function () {
    $own = HealthMedication::factory()->create(['user_id' => $this->user->id, 'name' => 'Levotiroxina']);
    HealthMedication::factory()->create(['name' => 'Levotiroxina ajena']);
    HealthMedicationIntake::factory()->create(['user_id' => $this->user->id, 'medication_id' => $own->id]);

    $this->get('/health/medications?search=levo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/medications/Index')
            ->where('medications.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Levotiroxina'])
            ->where('medications.data.0.intakes_count', 1)
            ->where('medications.data.0.intakes_max_taken_at', fn ($value) => $value !== null));
});

it('returns not found when the intake belongs to another medication', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);
    $other = HealthMedication::factory()->create(['user_id' => $this->user->id]);
    $intake = HealthMedicationIntake::factory()->create([
        'user_id' => $this->user->id,
        'medication_id' => $other->id,
    ]);

    $this->delete("/health/medications/{$medication->id}/intakes/{$intake->id}")->assertNotFound();

    expect(HealthMedicationIntake::find($intake->id))->not->toBeNull();
});

it('forbids another user medication and intake', function () {
    $medication = HealthMedication::factory()->create();
    $intake = HealthMedicationIntake::factory()->create([
        'user_id' => $medication->user_id,
        'medication_id' => $medication->id,
    ]);

    $this->put("/health/medications/{$medication->id}", ['name' => 'hack'])->assertForbidden();
    $this->post("/health/medications/{$medication->id}/intakes", ['taken_at' => now()])->assertForbidden();
    $this->delete("/health/medications/{$medication->id}/intakes/{$intake->id}")->assertForbidden();
});
