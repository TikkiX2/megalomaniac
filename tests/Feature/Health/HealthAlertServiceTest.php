<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Health\Enums\Severity;
use App\Models\HealthAlert;
use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\User;
use App\Services\Health\HealthAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates alert for severe symptom', function () {
    $symptom = HealthSymptom::factory()->create([
        'user_id' => $this->user->id,
        'severity' => Severity::Severe->value,
    ]);

    HealthAlertService::checkForAlerts($this->user);

    $alert = HealthAlert::where('user_id', $this->user->id)
        ->where('type', 'symptom_severe')
        ->where('related_id', $symptom->id)
        ->first();

    expect($alert)->not->toBeNull();
    expect($alert->is_read)->toBeFalse();
    expect($alert->message)->toContain('Síntoma severo detectado');
});

it('creates alert for high measurement', function () {
    $measurement = HealthMeasurement::factory()->create([
        'user_id' => $this->user->id,
        'flag' => 'high',
    ]);

    HealthAlertService::checkForAlerts($this->user);

    $alert = HealthAlert::where('user_id', $this->user->id)
        ->where('type', 'study_result_high')
        ->where('related_id', $measurement->id)
        ->first();

    expect($alert)->not->toBeNull();
    expect($alert->is_read)->toBeFalse();
    expect($alert->message)->toContain('Valor alto de medición detectado');
});

it('does not create duplicate alerts for same symptom', function () {
    $symptom = HealthSymptom::factory()->create([
        'user_id' => $this->user->id,
        'severity' => Severity::Severe->value,
    ]);

    HealthAlertService::checkForAlerts($this->user);
    HealthAlertService::checkForAlerts($this->user); // Second call

    $alertCount = HealthAlert::where('user_id', $this->user->id)
        ->where('type', 'symptom_severe')
        ->count();

    expect($alertCount)->toBe(1);
});
