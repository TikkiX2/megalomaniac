<?php

use App\Ai\Tools\HealthQueryTool;
use App\Models\HealthAppointment;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
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

it('queries studies, study results and appointments scoped to the user', function () {
    $user = User::factory()->create();

    $study = HealthStudy::factory()->create(['user_id' => $user->id, 'title' => 'Laboratorio TSH']);
    HealthStudyResult::factory()->create(['study_id' => $study->id, 'analyte' => 'TSH', 'value' => '2.5']);
    HealthAppointment::factory()->create(['user_id' => $user->id, 'title' => 'Control neurólogo']);

    $studies = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'studies'])), true);
    expect($studies['records'])->toHaveCount(1)
        ->and($studies['records'][0]['title'])->toBe('Laboratorio TSH');

    $results = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'study_results', 'search' => 'tsh'])), true);
    expect($results['records'])->toHaveCount(1)
        ->and($results['records'][0]['analyte'])->toBe('TSH');

    $appointments = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'appointments'])), true);
    expect($appointments['records'])->toHaveCount(1)
        ->and($appointments['records'][0]['title'])->toBe('Control neurólogo');
});
