<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds columns when a personal project is created', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    expect($project->boardColumns()->pluck('key')->all())->toBe(['Pending', 'In Progress', 'Done'])
        ->and($project->boardColumns()->firstWhere('key', 'Done')->is_done)->toBeTrue();
});

it('seeds columns when a freelance project is created', function () {
    $user = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    expect($project->boardColumns()->pluck('key')->all())->toBe(['To Do', 'In Progress', 'Done']);
});
