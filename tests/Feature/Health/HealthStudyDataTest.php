<?php

use App\Health\Enums\ResultFlag;
use App\Health\Enums\StudyType;
use App\Models\HealthStudy;
use App\Models\HealthStudyResult;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\HealthStudySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates health study with enum casts and relations', function () {
    $user = User::factory()->create();
    $study = HealthStudy::factory()->create([
        'user_id' => $user->id,
        'type' => StudyType::Lab,
    ]);

    expect($study->fresh()->type)->toBe(StudyType::Lab)
        ->and($study->user->is($user))->toBeTrue();
});

it('links study results to study and casts flag', function () {
    $study = HealthStudy::factory()->create();
    $result = HealthStudyResult::factory()->create([
        'study_id' => $study->id,
        'flag' => ResultFlag::Normal,
        'sort_order' => 1,
    ]);

    expect($result->fresh()->flag)->toBe(ResultFlag::Normal)
        ->and($result->study->is($study))->toBeTrue()
        ->and($study->results->contains($result))->toBeTrue();
});

it('creates a study linked to a family person', function () {
    $person = Person::factory()->create();
    $study = HealthStudy::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
    ]);

    expect($study->person->is($person))->toBeTrue();
});

it('seeder creates lab study with TSH and CK results for test user', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $this->seed(HealthStudySeeder::class);

    $study = HealthStudy::where('user_id', $user->id)
        ->where('type', StudyType::Lab)
        ->where('title', 'Laboratorio TSH/CK')
        ->first();

    expect($study)->not->toBeNull()
        ->and($study->results)->toHaveCount(2)
        ->and($study->results->pluck('analyte'))->toContain('TSH', 'CK');
});
