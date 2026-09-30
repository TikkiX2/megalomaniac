<?php

use App\Health\Enums\MeasurementType;
use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\HealthLogTool;
use App\Mcp\Tools\HealthReadTool;
use App\Mcp\Tools\HealthWriteTool;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, reads, updates and deletes conditions', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'create_condition', 'name' => 'Hipotiroidismo', 'kind' => 'diagnosis', 'status' => 'active'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('condition.name', 'Hipotiroidismo')->etc());

    $condition = HealthCondition::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthReadTool::class, ['resource' => 'conditions'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('records')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'update_condition', 'condition_id' => $condition->id, 'status' => 'resolved'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('condition.status', 'resolved')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'delete_condition', 'condition_id' => $condition->id])
        ->assertOk();

    expect(HealthCondition::find($condition->id))->toBeNull();
});

it('logs measurements through the log tool and syncs weight', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthLogTool::class, [
            'kind' => 'measurement',
            'type' => 'weight',
            'value' => 62.5,
            'unit' => 'kg',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('measurement')->etc());

    expect((float) $user->fresh()->weight)->toBe(62.5)
        ->and(HealthMeasurement::where('user_id', $user->id)->count())->toBe(1);
});

it('returns the summary and scopes tools to the authenticated user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    HealthMedication::factory()->create(['user_id' => $owner->id]);
    HealthCondition::factory()->create(['user_id' => $owner->id]);

    MegalomaniacServer::actingAs($owner)
        ->tool(HealthReadTool::class, ['resource' => 'summary'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('summary.active_medications')->etc());

    MegalomaniacServer::actingAs($intruder)
        ->tool(HealthWriteTool::class, ['action' => 'update_condition', 'condition_id' => $owner->healthConditions()->firstOrFail()->id, 'name' => 'hack'])
        ->assertHasErrors(['not found']);

    expect($owner->healthConditions()->first()->name)->not->toBe('hack');
});

it('never edits existing data through the log tool', function () {
    $user = User::factory()->create();
    $existing = HealthMeasurement::factory()->create([
        'user_id' => $user->id,
        'type' => MeasurementType::Weight,
        'value' => 70,
        'unit' => 'kg',
    ]);

    MegalomaniacServer::actingAs($user)
        ->tool(HealthLogTool::class, [
            'kind' => 'measurement',
            'type' => 'weight',
            'value' => 71,
            'unit' => 'kg',
            'action' => 'update_measurement',
            'measurement_id' => $existing->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('measurement')->etc());

    expect(HealthMeasurement::where('user_id', $user->id)->count())->toBe(2)
        ->and((float) $existing->fresh()->value)->toBe(70.0);
});
