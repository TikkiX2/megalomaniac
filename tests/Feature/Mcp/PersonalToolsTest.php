<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\PersonalProjectReadTool;
use App\Mcp\Tools\PersonalProjectWriteTool;
use App\Mcp\Tools\PersonalTaskReadTool;
use App\Mcp\Tools\PersonalTaskWriteTool;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, reads, updates and deletes personal projects', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalProjectWriteTool::class, ['action' => 'create', 'name' => 'Mi Proyecto'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.name', 'Mi Proyecto')
            ->where('project.type', 'personal')
            ->etc());

    $project = Project::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalProjectReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('records')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalProjectWriteTool::class, [
            'action' => 'update',
            'project_id' => $project->id,
            'status' => 'in_progress',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.status', 'in_progress')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalProjectWriteTool::class, ['action' => 'delete', 'project_id' => $project->id])
        ->assertOk();

    expect(Project::withTrashed()->find($project->id)->trashed())->toBeTrue();
});

it('creates, updates and deletes standalone personal tasks', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, ['action' => 'create', 'title' => 'Mi Tarea'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('task.title', 'Mi Tarea')
            ->etc());

    $task = ProjectTask::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, [
            'action' => 'update',
            'task_id' => $task->id,
            'title' => 'Mi Tarea Renombrada',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('task.title', 'Mi Tarea Renombrada')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('records')
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, ['action' => 'delete', 'task_id' => $task->id])
        ->assertOk();

    expect(ProjectTask::find($task->id))->toBeNull();
});

it('scopes personal tools to the authenticated user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'personal']);
    $task = ProjectTask::factory()->create(['user_id' => $owner->id, 'project_id' => null]);

    MegalomaniacServer::actingAs($intruder)
        ->tool(PersonalProjectWriteTool::class, ['action' => 'update', 'project_id' => $project->id, 'name' => 'hack'])
        ->assertHasErrors(['not found']);

    MegalomaniacServer::actingAs($intruder)
        ->tool(PersonalTaskWriteTool::class, ['action' => 'delete', 'task_id' => $task->id])
        ->assertHasErrors(['not found']);

    expect($project->fresh()->name)->not->toBe('hack')
        ->and(ProjectTask::find($task->id))->not->toBeNull();
});

it('moves a personal project to freelance through the mcp tool', function () {
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

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalProjectWriteTool::class, [
            'action' => 'update',
            'project_id' => $project->id,
            'type' => 'freelance',
            'client_id' => $client->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('project.type', 'freelance')
            ->etc());

    expect($task->fresh()->status)->toBe('To Do')
        ->and($project->fresh()->boardColumns()->pluck('key'))->toContain('To Do');
});

it('creates and moves tasks inside personal projects through mcp', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, [
            'action' => 'create',
            'title' => 'Dentro del proyecto',
            'project_id' => $project->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('task.status', 'Pending')
            ->where('task.project_id', $project->id)
            ->etc());

    $task = ProjectTask::where('title', 'Dentro del proyecto')->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, [
            'action' => 'move',
            'task_id' => $task->id,
            'status' => 'Done',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('task.is_done', true)
            ->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskWriteTool::class, [
            'action' => 'move',
            'task_id' => $task->id,
            'status' => 'Pending',
            'project_id' => $freelance->id,
        ])
        ->assertHasErrors(['personal module']);

    MegalomaniacServer::actingAs($user)
        ->tool(PersonalTaskReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('records')
            ->etc());
});
