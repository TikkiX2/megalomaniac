<?php

use App\Ai\Tools\HealthQueryTool;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function healthQueryTool(User $user): HealthQueryTool
{
    return new HealthQueryTool($user, app(HealthService::class));
}

it('returns the summary and filters records by resource', function () {
    $user = User::factory()->create();
    HealthCondition::factory()->create(['user_id' => $user->id, 'name' => 'Hipotiroidismo']);
    HealthMeasurement::factory()->create(['user_id' => $user->id]);
    HealthSymptom::factory()->create(['user_id' => $user->id, 'symptom' => 'calambre']);

    $summary = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'summary'])), true);

    expect($summary['summary']['active_conditions'])->toHaveCount(1)
        ->and($summary['summary']['recent_symptoms'])->toHaveCount(1);

    $conditions = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'conditions', 'search' => 'hipo'])), true);

    expect($conditions['records'])->toHaveCount(1)
        ->and($conditions['records'][0]['name'])->toBe('Hipotiroidismo');
});

it('scopes results to the authenticated user', function () {
    $user = User::factory()->create();
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $result = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'conditions'])), true);

    expect($result['records'])->toHaveCount(0);
});
