<?php

use App\Models\Currency;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskProperty;
use App\Models\TaskSavedView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    // Ensure currency exists for project creation
    if (Currency::count() === 0) {
        Currency::factory()->create();
    }
});

it('can create personal project', function () {
    $response = $this->post('/personal/projects', [
        'name' => 'Mi Proyecto Personal',
        'status' => 'pending',
        'color' => '#EF4444',
        'priority' => 'High',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('projects', [
        'name' => 'Mi Proyecto Personal',
        'type' => 'personal',
        'user_id' => $this->user->id,
    ]);
});

it('lists personal projects', function () {
    Project::factory()->count(2)->create(['type' => 'personal', 'user_id' => $this->user->id]);
    Project::factory()->create(['type' => 'freelance', 'user_id' => $this->user->id]);

    $response = $this->get('/personal/projects');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('personal/projects/Index'));
});

it('prevents accessing other user personal project', function () {
    $other = User::factory()->create();
    $project = Project::factory()->create(['type' => 'personal', 'user_id' => $other->id]);

    $this->get("/personal/projects/{$project->id}")->assertStatus(403);
});

it('can create personal task', function () {
    $project = Project::factory()->create(['type' => 'personal', 'user_id' => $this->user->id]);
    $response = $this->post('/personal/tasks', [
        'title' => 'Mi Tarea',
        'status' => 'Pending',
        'project_id' => $project->id,
        'priority' => 'High',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('project_tasks', [
        'title' => 'Mi Tarea',
        'project_id' => $project->id,
        'user_id' => $this->user->id,
    ]);
});

it('can create standalone personal task without project', function () {
    $response = $this->post('/personal/tasks', [
        'title' => 'Tarea Sin Proyecto',
        'status' => 'Pending',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('project_tasks', [
        'title' => 'Tarea Sin Proyecto',
        'user_id' => $this->user->id,
        'project_id' => null,
    ]);
});

it('lists personal tasks with filters', function () {
    $project = Project::factory()->create(['type' => 'personal', 'user_id' => $this->user->id]);
    ProjectTask::factory()->create(['project_id' => $project->id, 'user_id' => $this->user->id, 'status' => 'Pending', 'title' => 'Tarea Filtrada']);
    ProjectTask::factory()->create(['project_id' => $project->id, 'user_id' => $this->user->id, 'status' => 'Done', 'title' => 'Otra']);

    $response = $this->get('/personal/tasks?status=Pending');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('personal/tasks/Index'));
});

it('can move task status via kanban', function () {
    $task = ProjectTask::factory()->create(['user_id' => $this->user->id, 'status' => 'Pending']);
    $this->patch("/personal/tasks/{$task->id}/move", ['status' => 'Done'])->assertRedirect();
    expect($task->fresh()->status)->toBe('Done');
});

it('can add custom property to task', function () {
    $task = ProjectTask::factory()->create(['user_id' => $this->user->id]);
    $this->post("/personal/tasks/{$task->id}/properties", [
        'key' => 'Sprint',
        'type' => 'select',
        'value_json' => ['value' => 'S1', 'options' => ['S1', 'S2']],
    ])->assertRedirect();
    $this->assertDatabaseHas('task_properties', [
        'project_task_id' => $task->id,
        'key' => 'Sprint',
        'type' => 'select',
    ]);
});

it('prevents duplicate property keys per task', function () {
    $task = ProjectTask::factory()->create(['user_id' => $this->user->id]);
    TaskProperty::factory()->create(['project_task_id' => $task->id, 'key' => 'Sprint']);
    $this->post("/personal/tasks/{$task->id}/properties", [
        'key' => 'Sprint',
        'type' => 'text',
        'value_text' => 'dup',
    ])->assertSessionHasErrors('key');
});

it('can save and delete saved view', function () {
    $this->post('/personal/saved-views', [
        'name' => 'Mi Vista',
        'view_type' => 'table',
        'filters' => ['status' => 'Pending'],
    ])->assertRedirect();
    $this->assertDatabaseHas('task_saved_views', ['name' => 'Mi Vista', 'user_id' => $this->user->id]);

    $view = TaskSavedView::where('name', 'Mi Vista')->first();
    $this->delete("/personal/saved-views/{$view->id}")->assertRedirect();
    $this->assertDatabaseMissing('task_saved_views', ['id' => $view->id]);
});

it('completes full personal flow', function () {
    // Create project
    $this->post('/personal/projects', ['name' => 'Proyecto Flujo', 'status' => 'pending'])->assertRedirect();
    $project = Project::where('name', 'Proyecto Flujo')->first();
    expect($project->type)->toBe('personal');

    // Create task
    $this->post('/personal/tasks', ['title' => 'Tarea Flujo', 'project_id' => $project->id, 'status' => 'Pending'])->assertRedirect();
    $task = ProjectTask::where('title', 'Tarea Flujo')->first();
    expect($task->project_id)->toBe($project->id);

    // Add custom property
    $this->post("/personal/tasks/{$task->id}/properties", ['key' => 'Story Points', 'type' => 'number', 'value_number' => 5])->assertRedirect();

    // Filter
    $this->get('/personal/tasks?search=Tarea Flujo')->assertOk();

    // Move task
    $this->patch("/personal/tasks/{$task->id}/move", ['status' => 'Done'])->assertRedirect();
    expect($task->fresh()->status)->toBe('Done');
});
