<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Models\User;
use App\Services\TaskBoardColumnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates default columns lazily for a user', function () {
    $user = User::factory()->create();

    $columns = TaskBoardColumnService::defaultColumnsFor($user);

    expect($columns)->toHaveCount(3)
        ->and($columns->pluck('key')->all())->toBe(['Pending', 'In Progress', 'Done'])
        ->and($columns->firstWhere('key', 'Done')->is_done)->toBeTrue();
});

it('seeds personal and freelance projects with their vocabularies', function () {
    $user = User::factory()->create();
    $personal = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    TaskBoardColumnService::seedFor($personal);
    TaskBoardColumnService::seedFor($freelance);

    expect($personal->boardColumns()->pluck('key')->all())->toBe(['Pending', 'In Progress', 'Done'])
        ->and($freelance->boardColumns()->pluck('key')->all())->toBe(['To Do', 'In Progress', 'Done']);
});

it('does not duplicate columns when seeding twice', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    TaskBoardColumnService::seedFor($project);
    TaskBoardColumnService::seedFor($project);

    expect($project->boardColumns()->count())->toBe(3);
});

it('propagates is_done to tasks when toggling a column', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $task = ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'Pending', 'is_done' => false]);
    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $column->update(['is_done' => true]);
    TaskBoardColumnService::propagateIsDone($column);

    expect($task->fresh()->is_done)->toBeTrue();
});

it('resolves status keys for a project or the user default', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    expect(TaskBoardColumnService::statusKeys($project, $user))->toBe(['To Do', 'In Progress', 'Done'])
        ->and(TaskBoardColumnService::firstStatusKey($project, $user))->toBe('To Do')
        ->and(TaskBoardColumnService::statusKeys(null, $user))->toBe(['Pending', 'In Progress', 'Done'])
        ->and(TaskBoardColumnService::firstStatusKey(null, $user))->toBe('Pending');
});

it('copies a default column into a project scope on demand', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    TaskBoardColumn::create([
        'user_id' => $user->id,
        'project_id' => null,
        'key' => 'revision',
        'label' => 'Revisión',
        'color' => 'sky',
        'sort_order' => 3,
        'is_done' => false,
    ]);

    $column = TaskBoardColumnService::ensureColumnForScope($project, $user, 'revision');

    expect($column)->not->toBeNull()
        ->and($column->project_id)->toBe($project->id)
        ->and($column->label)->toBe('Revisión')
        ->and($project->boardColumns()->where('key', 'revision')->exists())->toBeTrue();
});

it('returns null when the status is unknown in both scopes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    expect(TaskBoardColumnService::ensureColumnForScope($project, $user, 'nope'))->toBeNull()
        ->and(TaskBoardColumnService::ensureColumnForScope(null, $user, 'nope'))->toBeNull();
});
