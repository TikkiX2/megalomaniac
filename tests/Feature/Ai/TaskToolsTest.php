<?php

use App\Ai\Tools\ActionTool;
use App\Ai\Tools\TaskQueryTool;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function personalTask(User $user, array $attributes = []): ProjectTask
{
    return ProjectTask::factory()->create(array_merge([
        'user_id' => $user->id,
        'project_id' => null,
        'status' => 'Pending',
        'is_done' => false,
        'is_archived' => false,
    ], $attributes));
}

it('lists pending tasks with a summary', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Rediseño']);

    personalTask($user, ['title' => 'Pagar impuestos', 'due_date' => now()->toDateString()]);
    personalTask($user, ['title' => 'Turno médico', 'due_date' => now()->subDays(2)]);
    ProjectTask::factory()->for($project)->create([
        'user_id' => $user->id,
        'title' => 'Wireframes',
        'status' => 'To Do',
        'is_done' => false,
        'due_date' => now()->addDays(3),
    ]);
    personalTask($user, ['title' => 'Vieja', 'is_done' => true, 'status' => 'Done']);

    $payload = json_decode((string) (new TaskQueryTool($user))->handle(new Request([])), true);

    expect($payload['tasks'])->toHaveCount(3)
        ->and($payload['summary']['pending'])->toBe(3)
        ->and($payload['summary']['overdue'])->toBe(1)
        ->and($payload['summary']['due_today'])->toBe(1)
        ->and(collect($payload['tasks'])->firstWhere('title', 'Wireframes')['project'])->toBe('Rediseño')
        ->and(collect($payload['tasks'])->firstWhere('title', 'Pagar impuestos')['scope'])->toBe('personal');
});

it('filters by scope, overdue, due window and search', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'App']);

    personalTask($user, ['title' => 'Comprar café']);
    ProjectTask::factory()->for($project)->create([
        'user_id' => $user->id, 'title' => 'Deploy', 'is_done' => false, 'due_date' => now()->addDay(),
    ]);
    personalTask($user, ['title' => 'Vencida', 'due_date' => now()->subWeek()]);

    $tool = new TaskQueryTool($user);

    $personal = json_decode((string) $tool->handle(new Request(['scope' => 'personal'])), true);
    expect($personal['tasks'])->toHaveCount(2);

    $freelance = json_decode((string) $tool->handle(new Request(['scope' => 'freelance'])), true);
    expect($freelance['tasks'])->toHaveCount(1)->and($freelance['tasks'][0]['title'])->toBe('Deploy');

    $overdue = json_decode((string) $tool->handle(new Request(['overdue' => true])), true);
    expect($overdue['tasks'])->toHaveCount(1)->and($overdue['tasks'][0]['title'])->toBe('Vencida');

    $window = json_decode((string) $tool->handle(new Request(['due_within_days' => 2])), true);
    expect($window['tasks'])->toHaveCount(1)->and($window['tasks'][0]['title'])->toBe('Deploy');

    $search = json_decode((string) $tool->handle(new Request(['search' => 'café'])), true);
    expect($search['tasks'])->toHaveCount(1);

    $byProject = json_decode((string) $tool->handle(new Request(['project' => 'App'])), true);
    expect($byProject['tasks'])->toHaveCount(1)->and($byProject['tasks'][0]['title'])->toBe('Deploy');
});

it('never returns other users tasks', function () {
    $user = User::factory()->create();
    personalTask(User::factory()->create(), ['title' => 'Ajena']);
    personalTask($user, ['title' => 'Mía']);

    $payload = json_decode((string) (new TaskQueryTool($user))->handle(new Request([])), true);

    expect($payload['tasks'])->toHaveCount(1)->and($payload['tasks'][0]['title'])->toBe('Mía');
});

it('completes and updates tasks through the action tool', function () {
    $user = User::factory()->create();
    $task = personalTask($user, ['title' => 'Cerrar mes']);
    $tool = new ActionTool($user);

    $completed = json_decode((string) $tool->handle(new Request([
        'action' => 'complete_task',
        'task_id' => $task->id,
    ])), true);

    expect($completed['success'])->toBeTrue()
        ->and($task->fresh()->is_done)->toBeTrue()
        ->and($task->fresh()->status)->toBe('Done');

    $updated = json_decode((string) $tool->handle(new Request([
        'action' => 'update_task',
        'task_id' => $task->id,
        'title' => 'Cerrar mes y año',
        'priority' => 'High',
        'due_date' => now()->addDays(5)->toDateString(),
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and($task->fresh()->title)->toBe('Cerrar mes y año')
        ->and($task->fresh()->priority)->toBe('High')
        ->and($task->fresh()->due_date->toDateString())->toBe(now()->addDays(5)->toDateString());
});

it('creates tasks with a due date and rejects foreign tasks', function () {
    $user = User::factory()->create();
    $tool = new ActionTool($user);

    $created = json_decode((string) $tool->handle(new Request([
        'action' => 'create_task',
        'title' => 'Nueva tarea',
        'due_date' => now()->addWeek()->toDateString(),
    ])), true);

    expect($created['success'])->toBeTrue()
        ->and(ProjectTask::where('title', 'Nueva tarea')->first()->due_date->toDateString())->toBe(now()->addWeek()->toDateString());

    $foreign = personalTask(User::factory()->create());

    $result = json_decode((string) $tool->handle(new Request([
        'action' => 'complete_task',
        'task_id' => $foreign->id,
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($foreign->fresh()->is_done)->toBeFalse();
});

it('creates projects and moves tasks between them through the action tool', function () {
    $user = User::factory()->create();
    $tool = new ActionTool($user);

    $created = json_decode((string) $tool->handle(new Request([
        'action' => 'create_project',
        'name' => 'Rediseño web',
        'type' => 'freelance',
        'description' => 'Landing nueva',
        'deadline' => now()->addMonth()->toDateString(),
    ])), true);

    expect($created['success'])->toBeTrue()
        ->and($created['project']['type'])->toBe('freelance');

    $project = Project::where('name', 'Rediseño web')->first();

    expect($project)->not->toBeNull()
        ->and($project->boardColumns()->pluck('key')->all())->toBe(['To Do', 'In Progress', 'Done'])
        ->and($project->description[0]['children'][0]['text'])->toBe('Landing nueva');

    $task = personalTask($user, ['title' => 'Moverme']);

    $moved = json_decode((string) $tool->handle(new Request([
        'action' => 'update_task',
        'task_id' => $task->id,
        'project_id' => $project->id,
    ])), true);

    expect($moved['success'])->toBeTrue()
        ->and($task->fresh()->project_id)->toBe($project->id)
        ->and($task->fresh()->status)->toBe('To Do');

    $detached = json_decode((string) $tool->handle(new Request([
        'action' => 'update_task',
        'task_id' => $task->id,
        'project_id' => 0,
    ])), true);

    expect($detached['success'])->toBeTrue()
        ->and($task->fresh()->project_id)->toBeNull()
        ->and($task->fresh()->status)->toBe('Pending');
});

it('rejects moving a task to a foreign project', function () {
    $user = User::factory()->create();
    $foreignProject = Project::factory()->create();
    $task = personalTask($user);
    $tool = new ActionTool($user);

    $result = json_decode((string) $tool->handle(new Request([
        'action' => 'update_task',
        'task_id' => $task->id,
        'project_id' => $foreignProject->id,
    ])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and($task->fresh()->project_id)->toBeNull();
});

it('rejects creating a project without a name', function () {
    $tool = new ActionTool(User::factory()->create());

    $result = json_decode((string) $tool->handle(new Request(['action' => 'create_project'])), true);

    expect($result['success'] ?? false)->toBeFalse()
        ->and(Project::count())->toBe(0);
});
