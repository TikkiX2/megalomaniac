<?php

use App\Ai\Tools\HealthActionTool;
use App\Health\Enums\IntakeStatus;
use App\Models\HealthCondition;
use App\Models\HealthMedication;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function healthTool(User $user): HealthActionTool
{
    return new HealthActionTool($user, app(HealthService::class));
}

it('creates conditions, medications and logs events', function () {
    $user = User::factory()->create();

    $condition = json_decode(healthTool($user)->handle(new Request([
        'action' => 'create_condition',
        'name' => 'Hipotiroidismo',
        'kind' => 'diagnosis',
        'status' => 'active',
    ])), true);

    expect($condition['success'])->toBeTrue();

    $medication = json_decode(healthTool($user)->handle(new Request([
        'action' => 'create_medication',
        'name' => 'Levotiroxina',
        'dose_amount' => 50,
        'dose_unit' => 'mcg',
    ])), true);

    expect($medication['success'])->toBeTrue();

    $medicationId = HealthMedication::where('user_id', $user->id)->firstOrFail()->id;

    $intake = json_decode(healthTool($user)->handle(new Request([
        'action' => 'log_intake',
        'medication_id' => $medicationId,
        'status' => IntakeStatus::Taken->value,
    ])), true);

    expect($intake['success'])->toBeTrue()
        ->and(HealthCondition::where('user_id', $user->id)->exists())->toBeTrue();
});

it('rejects updates with no fields to apply', function () {
    $user = User::factory()->create();
    $condition = HealthCondition::factory()->create(['user_id' => $user->id]);

    $empty = json_decode(healthTool($user)->handle(new Request([
        'action' => 'update_condition',
        'condition_id' => $condition->id,
    ])), true);

    expect($empty['success'])->toBeFalse()
        ->and($empty['error'])->toContain('No fields to update.');

    $updated = json_decode(healthTool($user)->handle(new Request([
        'action' => 'update_condition',
        'condition_id' => $condition->id,
        'name' => 'Nombre actualizado',
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and($updated['condition']['name'])->toBe('Nombre actualizado');
});

it('returns domain errors instead of throwing', function () {
    $user = User::factory()->create();

    $result = json_decode(healthTool($user)->handle(new Request([
        'action' => 'update_condition',
        'condition_id' => 999,
        'name' => 'x',
    ])), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('not found');
});

it('rejects foreign keys owned by another user and stores timestamps in UTC', function () {
    $user = User::factory()->create();
    $foreign = HealthCondition::factory()->create();

    $rejected = json_decode(healthTool($user)->handle(new Request([
        'action' => 'create_medication',
        'name' => 'Levotiroxina',
        'condition_id' => $foreign->id,
    ])), true);

    expect($rejected['success'])->toBeFalse()
        ->and(HealthMedication::where('user_id', $user->id)->exists())->toBeFalse();

    $measured = json_decode(healthTool($user)->handle(new Request([
        'action' => 'log_measurement',
        'type' => 'weight',
        'value' => 70,
        'unit' => 'kg',
        'measured_at' => '2026-09-30T10:00:00-03:00',
    ])), true);

    expect($measured['success'])->toBeTrue()
        ->and($measured['measurement']['measured_at'])->toContain('13:00:00');
});

it('requires approval with a readable label and never diagnoses', function () {
    $user = User::factory()->create();

    $approval = healthTool($user)->needsApproval(new Request(['action' => 'create_condition']));

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->reason)->toContain('crear')
        ->and((string) healthTool($user)->description())->toContain('never diagnose');
});
