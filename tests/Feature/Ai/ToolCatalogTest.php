<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\AskUserTool;
use App\Ai\Tools\FinanceActionTool;
use App\Ai\Tools\FreelanceActionTool;
use App\Ai\Tools\GroceryActionTool;
use App\Ai\Tools\GymActionTool;
use App\Ai\Tools\GymQueryTool;
use App\Ai\Tools\HealthActionTool;
use App\Ai\Tools\HealthQueryTool;
use App\Ai\Tools\NutritionActionTool;
use App\Ai\Tools\PeopleActionTool;
use App\Ai\Tools\PeopleQueryTool;
use App\Ai\Tools\ProjectActionTool;
use App\Ai\Tools\SupplementActionTool;
use App\Ai\Tools\TaskActionTool;
use App\Ai\Tools\TaskQueryTool;
use App\Ai\Tools\ToolCatalog;
use App\Models\User;

it('exposes the tool groups', function () {
    expect(ToolCatalog::allGroups())->toBe([
        'tasks', 'workout', 'finance', 'nutrition', 'grocery', 'supplements', 'freelance', 'people', 'health', 'actions', 'integrations', 'agents', 'skills', 'memory', 'web',
    ])
        ->and(ToolCatalog::isValidGroup('tasks'))->toBeTrue()
        ->and(ToolCatalog::isValidGroup('nope'))->toBeFalse();
});

it('builds only the requested groups', function () {
    $user = User::factory()->create();

    $tools = collect(ToolCatalog::toolsFor($user, ['tasks', 'actions']))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($tools)->toBe([
        TaskQueryTool::class,
        ProjectActionTool::class,
        TaskActionTool::class,
        GymActionTool::class,
        FinanceActionTool::class,
        NutritionActionTool::class,
        GroceryActionTool::class,
        SupplementActionTool::class,
        FreelanceActionTool::class,
        PeopleActionTool::class,
        HealthActionTool::class,
    ]);
});

it('builds every tool for the wildcard', function () {
    $tools = collect(ToolCatalog::toolsFor(User::factory()->create(), ['*']))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($tools)->toHaveCount(28)
        ->toContain(TaskQueryTool::class, ProjectActionTool::class, GymQueryTool::class, GymActionTool::class)
        ->toContain(PeopleQueryTool::class, PeopleActionTool::class)
        ->toContain(HealthQueryTool::class, HealthActionTool::class);
});

it('lets the main agent be built with a tool subset', function () {
    $user = User::factory()->create();

    $subset = collect(iterator_to_array((new MegalomaniacAgent($user, ['tasks']))->tools()))
        ->map(fn ($tool): string => $tool::class)
        ->all();

    expect($subset)->toBe([TaskQueryTool::class, ProjectActionTool::class, TaskActionTool::class, AskUserTool::class]);

    $all = collect(iterator_to_array((new MegalomaniacAgent($user))->tools()))->count();
    expect($all)->toBe(29);
});
