<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

test('task can be moved to another column and reordered', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $taskA = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'status' => 'To Do',
        'sort_order' => 0,
    ]);
    $taskB = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'status' => 'To Do',
        'sort_order' => 1,
    ]);
    $taskC = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'status' => 'In Progress',
        'sort_order' => 0,
    ]);

    actingAs($user)
        ->patchJson(route('freelance.tasks.move', $taskA), [
            'status' => 'In Progress',
            'ordered_ids' => [$taskC->id, $taskA->id],
        ])
        ->assertOk();

    expect($taskA->fresh()->status)->toBe('In Progress')
        ->and($taskA->fresh()->sort_order)->toBe(1)
        ->and($taskC->fresh()->sort_order)->toBe(0);

    // TaskB is unaffected (different column)
    expect($taskB->fresh()->sort_order)->toBe(1);
});

test('move ignores task ids from another project', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['user_id' => $user->id]);
    $projectB = Project::factory()->create(['user_id' => $user->id]);

    $task = ProjectTask::factory()->create([
        'project_id' => $projectA->id,
        'status' => 'To Do',
        'sort_order' => 0,
    ]);

    $foreignTask = ProjectTask::factory()->create([
        'project_id' => $projectB->id,
        'status' => 'To Do',
        'sort_order' => 5,
    ]);

    actingAs($user)
        ->patchJson(route('freelance.tasks.move', $task), [
            'status' => 'In Progress',
            'ordered_ids' => [$task->id, $foreignTask->id],
        ])
        ->assertOk();

    expect($task->fresh()->status)->toBe('In Progress')
        ->and($task->fresh()->sort_order)->toBe(0);

    // Foreign task sort_order is untouched
    expect($foreignTask->fresh()->sort_order)->toBe(5);
});

test('move requires status and ordered ids', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $task = ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'To Do']);

    actingAs($user)
        ->patchJson(route('freelance.tasks.move', $task), [])
        ->assertUnprocessable();

    actingAs($user)
        ->patchJson(route('freelance.tasks.move', $task), ['status' => 'Done'])
        ->assertUnprocessable();
});

test('move redirects back for inertia requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $task = ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'To Do']);

    actingAs($user)
        ->patch(route('freelance.tasks.move', $task), [
            'status' => 'In Progress',
            'ordered_ids' => [$task->id],
        ])
        ->assertRedirect();

    expect($task->fresh()->status)->toBe('In Progress');
});

test('new task gets correct sort_order', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    ProjectTask::factory()->create([
        'project_id' => $project->id,
        'sort_order' => 3,
    ]);

    actingAs($user)
        ->post(route('freelance.projects.tasks.store', $project), [
            'title' => 'Test Task',
            'status' => 'To Do',
        ])
        ->assertRedirect();

    assertDatabaseHas('project_tasks', [
        'project_id' => $project->id,
        'title' => 'Test Task',
        'sort_order' => 4,
    ]);
});
