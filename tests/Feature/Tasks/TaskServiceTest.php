<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;

uses(RefreshDatabase::class);

it('creates tasks in personal and freelance projects with the right board column', function () {
    $user = User::factory()->create();
    $personal = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);
    $service = app(TaskService::class);

    $personalTask = $service->create($user, ['title' => 'Personal', 'project_id' => $personal->id], 'personal');
    $freelanceTask = $service->create($user, ['title' => 'Freelance', 'project_id' => $freelance->id], 'freelance');

    expect($personalTask->status)->toBe('Pending')
        ->and($personalTask->is_done)->toBeFalse()
        ->and($freelanceTask->status)->toBe('To Do');
});

it('rejects a project of another user or another module', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Project::factory()->create(['user_id' => $other->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    expect(fn () => app(TaskService::class)->create($user, ['title' => 'X', 'project_id' => $foreign->id], 'personal'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(TaskService::class)->create($user, ['title' => 'X', 'project_id' => $freelance->id], 'personal'))
        ->toThrow(InvalidArgumentException::class);
});

it('updates status and syncs is_done with the board column', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $service = app(TaskService::class);

    $task = $service->create($user, ['title' => 'Tarea', 'project_id' => $project->id], 'personal');

    $done = $service->update($user, $task, ['status' => 'Done']);

    expect($done->status)->toBe('Done')
        ->and($done->is_done)->toBeTrue();

    expect(fn () => $service->update($user, $task, ['status' => 'Nope']))
        ->toThrow(InvalidArgumentException::class);
});

it('moves tasks between projects and reorders ordered ids', function () {
    $user = User::factory()->create();
    $from = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $to = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);
    $service = app(TaskService::class);

    $taskA = $service->create($user, ['title' => 'A', 'project_id' => $from->id], 'personal');
    $taskB = $service->create($user, ['title' => 'B', 'project_id' => $to->id], 'freelance');

    $moved = $service->move($user, $taskA, [
        'status' => 'To Do',
        'project_id' => $to->id,
        'ordered_ids' => [$taskB->id, $taskA->id],
    ]);

    expect($moved->project_id)->toBe($to->id)
        ->and($moved->status)->toBe('To Do')
        ->and($taskB->fresh()->sort_order)->toBe(0)
        ->and($moved->sort_order)->toBe(1);
});

it('completes and deletes tasks with ownership checks', function () {
    $user = User::factory()->create();
    $service = app(TaskService::class);
    $task = $service->create($user, ['title' => 'Suelta']);

    $done = $service->complete($user, $task);
    expect($done->is_done)->toBeTrue();

    $intruder = User::factory()->create();
    expect(fn () => $service->delete($intruder, $task))
        ->toThrow(AuthorizationException::class);

    $service->delete($user, $task);
    expect(ProjectTask::find($task->id))->toBeNull();
});
