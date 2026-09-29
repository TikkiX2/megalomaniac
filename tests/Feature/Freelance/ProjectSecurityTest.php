<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('forbids strangers from reading or mutating a freelance project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'freelance']);

    $this->actingAs($intruder)->get("/freelance/projects/{$project->id}")->assertForbidden();
    $this->actingAs($intruder)->get("/freelance/projects/{$project->id}/edit")->assertForbidden();
    $this->actingAs($intruder)->put("/freelance/projects/{$project->id}", [
        'name' => 'hack',
        'status' => 'pending',
    ])->assertForbidden();
    $this->actingAs($intruder)->delete("/freelance/projects/{$project->id}")->assertForbidden();
    $this->actingAs($intruder)->post("/freelance/projects/{$project->id}/payments", [
        'amount' => 10,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'cash',
        'status' => 'pending',
    ])->assertForbidden();
    $this->actingAs($intruder)->post("/freelance/projects/{$project->id}/media", [])->assertForbidden();

    expect($project->fresh())->not->toBeNull();
});

it('lets the owner manage their freelance project', function () {
    $owner = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $owner->id]);
    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'type' => 'freelance',
        'client_id' => $client->id,
    ]);

    $this->actingAs($owner)->get("/freelance/projects/{$project->id}")->assertOk();
    $this->actingAs($owner)->get("/freelance/projects/{$project->id}/edit")->assertOk();
});

it('forbids media endpoints for a foreign project media', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'freelance']);

    $media = $project->addMediaFromString('fake-image')
        ->usingFileName('shot.jpg')
        ->toMediaCollection('attachments');

    $this->actingAs($intruder)->get("/freelance/media/{$media->id}/download")->assertForbidden();
    $this->actingAs($intruder)->delete("/freelance/media/{$media->id}")->assertForbidden();

    expect($media->fresh())->not->toBeNull();
});

it('forbids strangers from mutating freelance tasks', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'freelance']);

    $task = ProjectTask::factory()->create(['user_id' => $owner->id, 'project_id' => $project->id]);

    $this->actingAs($intruder)->patch("/freelance/tasks/{$task->id}/move", [
        'status' => 'To Do',
        'ordered_ids' => [$task->id],
    ])->assertForbidden();

    $this->actingAs($intruder)->put("/freelance/tasks/{$task->id}", [
        'title' => 'hack',
        'status' => 'To Do',
    ])->assertForbidden();

    $this->actingAs($intruder)->delete("/freelance/tasks/{$task->id}")->assertForbidden();

    expect($task->fresh()->title)->not->toBe('hack');
});
