<?php

use App\Ai\Tools\ProjectActionTool;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function projectTool(User $user): ProjectActionTool
{
    return new ProjectActionTool($user, app(ProjectService::class));
}

it('requires an explicit type instead of defaulting to personal', function () {
    $user = User::factory()->create();

    $result = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'create_project',
        'name' => 'Nuevo',
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($result['error'])->toContain('type')
        ->and(Project::where('user_id', $user->id)->exists())->toBeFalse();
});

it('requires a name for project creation', function () {
    $user = User::factory()->create();

    $result = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'create_project',
        'type' => 'personal',
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and(Project::where('user_id', $user->id)->exists())->toBeFalse();
});

it('creates a project with the requested type and markdown description', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);

    $result = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'create_project',
        'name' => 'Rediseño web',
        'type' => 'freelance',
        'client_id' => $client->id,
        'description' => 'Landing nueva',
        'deadline' => now()->addMonth()->toDateString(),
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and($result['project']['type'])->toBe('freelance');

    $project = Project::where('name', 'Rediseño web')->first();
    $blocks = collect($project->description)->sortBy(fn (array $block): int => $block['meta']['order'] ?? 0)->values();

    expect($project)->not->toBeNull()
        ->and($project->boardColumns()->pluck('key')->all())->toBe(['To Do', 'In Progress', 'Done'])
        ->and($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('Paragraph')
        ->and($blocks[0]['value'][0]['children'][0]['text'])->toBe('Landing nueva');
});

it('moves a personal project to freelance through update_project', function () {
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

    $result = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'update_project',
        'project_id' => $project->id,
        'type' => 'freelance',
        'client_id' => $client->id,
        'name' => 'Movido',
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and($result['project']['type'])->toBe('freelance')
        ->and($project->fresh()->name)->toBe('Movido')
        ->and($task->fresh()->status)->toBe('To Do');
});

it('requires a client to move a project to freelance', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    $result = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'update_project',
        'project_id' => $project->id,
        'type' => 'freelance',
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($project->fresh()->type)->toBe('personal');
});

it('rejects updating a foreign project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'personal']);

    $result = json_decode((string) projectTool($intruder)->handle(new Request([
        'action' => 'update_project',
        'project_id' => $project->id,
        'name' => 'hack',
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($project->fresh()->name)->not->toBe('hack');
});

it('archives and deletes projects', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    $archived = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'archive_project',
        'project_id' => $project->id,
    ])), true);

    expect($archived['success'])->toBeTrue()
        ->and($project->fresh()->is_archived)->toBeTrue();

    $deleted = json_decode((string) projectTool($user)->handle(new Request([
        'action' => 'delete_project',
        'project_id' => $project->id,
    ])), true);

    expect($deleted['success'])->toBeTrue()
        ->and(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});
