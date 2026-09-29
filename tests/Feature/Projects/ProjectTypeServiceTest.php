<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Projects\ProjectTypeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('moves a personal project to freelance and remaps task statuses', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'type' => 'personal',
        'client_id' => null,
    ]);

    $done = ProjectTask::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Hecha',
        'status' => 'Done',
        'is_done' => true,
        'sort_order' => 1,
    ]);

    $pending = ProjectTask::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Pendiente',
        'status' => 'Pending',
        'is_done' => false,
        'sort_order' => 2,
    ]);

    $moved = app(ProjectTypeService::class)->changeType($user, $project, 'freelance', $client->id);

    expect($moved->type)->toBe('freelance')
        ->and($moved->client_id)->toBe($client->id)
        ->and($moved->boardColumns()->pluck('key'))->toContain('To Do', 'Done')
        ->and($moved->boardColumns()->pluck('key'))->not->toContain('Pending')
        ->and($done->fresh()->status)->toBe('Done')
        ->and($done->fresh()->is_done)->toBeTrue()
        ->and($pending->fresh()->status)->toBe('To Do')
        ->and($pending->fresh()->is_done)->toBeFalse();
});

it('clears the auto Personal client when moving back to personal', function () {
    $user = User::factory()->create();

    $auto = Client::firstOrCreate(
        ['user_id' => $user->id, 'name' => 'Personal'],
        ['email' => null, 'phone' => null],
    );

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'type' => 'freelance',
        'client_id' => $auto->id,
    ]);

    $moved = app(ProjectTypeService::class)->changeType($user, $project, 'personal');

    expect($moved->type)->toBe('personal')
        ->and($moved->client_id)->toBeNull();
});

it('rejects moving to freelance without a real client', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    expect(fn () => app(ProjectTypeService::class)->changeType($user, $project, 'freelance'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects moving another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'personal']);

    expect(fn () => app(ProjectTypeService::class)->changeType($intruder, $project, 'personal'))
        ->toThrow(AuthorizationException::class);
});
