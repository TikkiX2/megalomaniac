<?php

use App\Models\Client;
use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a personal project without the auto Personal client', function () {
    $user = User::factory()->create();
    $currency = Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);

    $project = app(ProjectService::class)->create($user, [
        'name' => 'Solo',
        'type' => 'personal',
        'status' => 'pending',
    ]);

    expect($project->type)->toBe('personal')
        ->and($project->client_id)->toBeNull()
        ->and($project->currency_id)->toBe($currency->id)
        ->and(Client::where('user_id', $user->id)->where('name', 'Personal')->exists())->toBeFalse();
});

it('requires a valid type when creating a project', function () {
    $user = User::factory()->create();

    expect(fn () => app(ProjectService::class)->create($user, ['name' => 'X']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(ProjectService::class)->create($user, ['name' => 'X', 'type' => 'nope']))
        ->toThrow(InvalidArgumentException::class);
});

it('requires an owned client for freelance projects', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreignClient = Client::factory()->create(['user_id' => $other->id]);

    expect(fn () => app(ProjectService::class)->create($user, ['name' => 'X', 'type' => 'freelance']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => app(ProjectService::class)->create($user, [
        'name' => 'X',
        'type' => 'freelance',
        'client_id' => $foreignClient->id,
    ]))->toThrow(ModelNotFoundException::class);
});

it('updates a project and delegates type changes to the type service', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal', 'client_id' => null]);

    $task = ProjectTask::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Pendiente',
        'status' => 'Pending',
        'is_done' => false,
        'sort_order' => 1,
    ]);

    $updated = app(ProjectService::class)->update($user, $project, [
        'name' => 'Movido',
        'type' => 'freelance',
        'client_id' => $client->id,
    ]);

    expect($updated->name)->toBe('Movido')
        ->and($updated->type)->toBe('freelance')
        ->and($task->fresh()->status)->toBe('To Do');
});

it('rejects updating another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'personal']);

    expect(fn () => app(ProjectService::class)->update($intruder, $project, ['name' => 'hack']))
        ->toThrow(AuthorizationException::class);
});

it('archives and deletes owned projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    $archived = app(ProjectService::class)->archive($user, $project);
    expect($archived->is_archived)->toBeTrue();

    app(ProjectService::class)->delete($user, $archived);
    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});
