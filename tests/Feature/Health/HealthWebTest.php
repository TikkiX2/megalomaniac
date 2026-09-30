<?php

use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the health dashboard with the user summary only', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Hipotiroidismo']);
    HealthCondition::factory()->create(['name' => 'Ajena']);
    HealthMedication::factory()->create(['user_id' => $this->user->id]);
    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);
    HealthSymptom::factory()->create(['user_id' => $this->user->id]);

    $this->get('/health')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/Dashboard')
            ->where('summary.active_conditions', fn ($rows) => collect($rows)->pluck('name')->contains('Hipotiroidismo'))
            ->where('summary.active_conditions', fn ($rows) => ! collect($rows)->pluck('name')->contains('Ajena'))
            ->has('summary.last_measurements', 1)
            ->has('summary.recent_symptoms', 1));
});

it('requires authentication for health routes', function () {
    auth()->logout();

    $this->get('/health')->assertRedirect('/login');
});

it('creates, updates and deletes a condition', function () {
    $this->post('/health/conditions', [
        'kind' => 'diagnosis',
        'name' => 'Hipotiroidismo',
        'status' => 'active',
        'severity' => 'moderate',
    ])->assertRedirect(route('health.conditions.index'));

    $condition = HealthCondition::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/conditions/{$condition->id}", ['status' => 'resolved'])
        ->assertRedirect(route('health.conditions.index'));
    expect($condition->fresh()->status->value)->toBe('resolved');

    $this->delete("/health/conditions/{$condition->id}")->assertRedirect(route('health.conditions.index'));
    expect(HealthCondition::find($condition->id))->toBeNull();
});

it('lists and filters conditions of the authenticated user', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Miopatía', 'status' => 'suspected']);
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $this->get('/health/conditions?search=mio')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/conditions/Index')
            ->where('conditions.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Miopatía']));
});

it('forbids editing another user condition', function () {
    $condition = HealthCondition::factory()->create();

    $this->put("/health/conditions/{$condition->id}", ['name' => 'hack'])->assertForbidden();

    expect($condition->fresh()->name)->not->toBe('hack');
});
