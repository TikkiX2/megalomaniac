<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\ActionTool;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\TaskQueryTool;
use App\Ai\Tools\ToolCatalog;
use App\Ai\Tools\WorkoutQueryTool;
use App\Models\User;

it('exposes the tool groups', function () {
    expect(ToolCatalog::allGroups())->toBe([
        'tasks', 'workout', 'finance', 'nutrition', 'grocery', 'actions', 'integrations', 'agents', 'web',
    ])
        ->and(ToolCatalog::isValidGroup('tasks'))->toBeTrue()
        ->and(ToolCatalog::isValidGroup('nope'))->toBeFalse();
});

it('builds only the requested groups', function () {
    $user = User::factory()->create();

    $tools = collect(ToolCatalog::toolsFor($user, ['tasks', 'actions']))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($tools)->toBe([TaskQueryTool::class, ActionTool::class]);
});

it('builds every tool for the wildcard', function () {
    $tools = collect(ToolCatalog::toolsFor(User::factory()->create(), ['*']))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($tools)->toHaveCount(11)
        ->toContain(TaskQueryTool::class, WorkoutQueryTool::class, ActionTool::class);
});

it('lets the main agent be built with a tool subset', function () {
    $user = User::factory()->create();

    $subset = collect(iterator_to_array((new MegalomaniacAgent($user, ['tasks']))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($subset)->toBe([TaskQueryTool::class, AskUserTool::class]);

    $all = collect(iterator_to_array((new MegalomaniacAgent($user))->tools()))->count();
    expect($all)->toBe(12);
});
