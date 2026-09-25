<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Models\User;
use App\Services\TaskBoardColumnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a column for the authenticated user', function () {
    $this->postJson(route('task-board-columns.store'), ['label' => 'Revisión'])
        ->assertOk()
        ->assertJsonPath('column.key', 'revision');

    $this->assertDatabaseHas('task_board_columns', [
        'user_id' => $this->user->id,
        'project_id' => null,
        'key' => 'revision',
        'label' => 'Revisión',
    ]);
});

it('generates unique keys within a scope', function () {
    $this->postJson(route('task-board-columns.store'), ['label' => 'Revisión'])->assertOk();

    $this->postJson(route('task-board-columns.store'), ['label' => 'Revisión'])
        ->assertOk()
        ->assertJsonPath('column.key', 'revision_2');
});

it('creates a column inside a project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);

    $this->postJson(route('task-board-columns.store'), ['label' => 'Revisión', 'project_id' => $project->id])
        ->assertOk()
        ->assertJsonPath('column.key', 'revision');

    $this->assertDatabaseHas('task_board_columns', [
        'project_id' => $project->id,
        'key' => 'revision',
    ]);
});

it('forbids creating a column in another user project', function () {
    $other = Project::factory()->create(['type' => 'personal']);

    $this->postJson(route('task-board-columns.store'), ['label' => 'X', 'project_id' => $other->id])
        ->assertNotFound();
});

it('updates label and propagates is_done without changing the key', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'status' => 'Pending',
        'is_done' => false,
    ]);
    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $this->patchJson(route('task-board-columns.update', $column), [
        'label' => 'Backlog',
        'is_done' => true,
    ])
        ->assertOk()
        ->assertJsonPath('column.label', 'Backlog')
        ->assertJsonPath('column.is_done', true);

    expect($task->fresh()->is_done)->toBeTrue()
        ->and($column->fresh()->key)->toBe('Pending');
});

it('forbids editing another user column', function () {
    $other = User::factory()->create();
    $column = TaskBoardColumn::create([
        'user_id' => $other->id,
        'key' => 'pending',
        'label' => 'Pending',
        'sort_order' => 0,
        'is_done' => false,
    ]);

    $this->patchJson(route('task-board-columns.update', $column), ['label' => 'Hack'])
        ->assertForbidden();

    $this->deleteJson(route('task-board-columns.destroy', $column))
        ->assertForbidden();
});

it('requires move_to when deleting a column with tasks', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'Pending']);
    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $this->deleteJson(route('task-board-columns.destroy', $column))
        ->assertUnprocessable();

    $this->assertDatabaseHas('task_board_columns', ['id' => $column->id]);
});

it('moves tasks to the destination column and deletes the column', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $task = ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'Pending', 'is_done' => false]);
    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $this->deleteJson(route('task-board-columns.destroy', $column), ['move_to' => 'In Progress'])
        ->assertOk();

    expect($task->fresh()->status)->toBe('In Progress')
        ->and($task->fresh()->is_done)->toBeFalse();
    $this->assertDatabaseMissing('task_board_columns', ['id' => $column->id]);
});

it('deletes a column without tasks without move_to', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $this->deleteJson(route('task-board-columns.destroy', $column))->assertOk();

    $this->assertDatabaseMissing('task_board_columns', ['id' => $column->id]);
});

it('propagates default column updates to project copies', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $default = TaskBoardColumnService::defaultColumnsFor($this->user)->firstWhere('key', 'Pending');
    $copy = $project->boardColumns()->where('key', 'Pending')->first();

    $this->patchJson(route('task-board-columns.update', $default), ['label' => 'Backlog'])
        ->assertOk();

    expect($copy->fresh()->label)->toBe('Backlog')
        ->and($default->fresh()->label)->toBe('Backlog');
});

it('deletes a default column moving project tasks to the destination', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $default = TaskBoardColumnService::defaultColumnsFor($this->user)->firstWhere('key', 'Pending');
    $task = ProjectTask::factory()->create([
        'project_id' => $project->id,
        'user_id' => $this->user->id,
        'status' => 'Pending',
        'is_done' => false,
    ]);

    $this->deleteJson(route('task-board-columns.destroy', $default), ['move_to' => 'In Progress'])
        ->assertOk();

    expect($task->fresh()->status)->toBe('In Progress')
        ->and($project->boardColumns()->where('key', 'Pending')->exists())->toBeFalse()
        ->and(TaskBoardColumn::where('user_id', $this->user->id)->where('key', 'Pending')->exists())->toBeFalse();
});

it('reorders columns', function () {
    $user = $this->user;
    $columns = TaskBoardColumnService::defaultColumnsFor($user);
    $orderedIds = [$columns[2]->id, $columns[0]->id, $columns[1]->id];

    $this->patchJson(route('task-board-columns.reorder'), ['ordered_ids' => $orderedIds])
        ->assertOk();

    expect(TaskBoardColumn::find($orderedIds[0])->sort_order)->toBe(0)
        ->and(TaskBoardColumn::find($orderedIds[1])->sort_order)->toBe(1)
        ->and(TaskBoardColumn::find($orderedIds[2])->sort_order)->toBe(2);
});
