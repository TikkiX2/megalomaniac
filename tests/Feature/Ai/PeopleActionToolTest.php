<?php

use App\Ai\Tools\PeopleActionTool;
use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function peopleTool(User $user): PeopleActionTool
{
    return new PeopleActionTool($user, app(PeopleService::class));
}

it('creates a person and logs an interaction', function () {
    $user = User::factory()->create();

    $created = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'create_person',
        'first_name' => 'Ana',
        'closeness' => 'close',
    ])), true);

    expect($created['success'])->toBeTrue();

    $person = Person::where('user_id', $user->id)->firstOrFail();

    $logged = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'log_interaction',
        'person_id' => $person->id,
        'channel' => 'call',
        'title' => 'Llamada',
    ])), true);

    expect($logged['success'])->toBeTrue()
        ->and($person->fresh()->last_contacted_at)->not->toBeNull();
});

it('returns an error instead of throwing on invalid enum or date values', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    $invalidCloseness = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'update_person',
        'person_id' => $person->id,
        'closeness' => 'buddy',
    ])), true);

    expect($invalidCloseness['success'])->toBeFalse()
        ->and($invalidCloseness['error'])->toContain('buddy');

    $invalidDate = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'add_key_date',
        'person_id' => $person->id,
        'key_date_type' => 'custom',
        'date' => 'not-a-date',
    ])), true);

    expect($invalidDate['success'])->toBeFalse();

    $invalidType = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'add_key_date',
        'person_id' => $person->id,
        'key_date_type' => 'nope',
        'date' => '2026-12-01',
    ])), true);

    expect($invalidType['success'])->toBeFalse();
});

it('requires approval with a readable label', function () {
    $user = User::factory()->create();

    $approval = peopleTool($user)->needsApproval(new Request(['action' => 'create_person']));

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->reason)->toContain('crear');
});
