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
