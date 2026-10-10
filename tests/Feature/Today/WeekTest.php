<?php

use App\Models\ProjectTask;
use App\Models\User;

test('week candidates lista pendientes fuera del pool en orden de creacion ascendente', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $vieja = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Vieja', 'created_at' => now()->subDays(30)]);
    $nueva = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Nueva', 'created_at' => now()->subDay()]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Hecha', 'is_done' => true]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Archivada', 'archived_at' => now()]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'En pool', 'in_week' => true]);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($props['candidates'])->toHaveCount(2);
    expect($titles)->toContain('Vieja', 'Nueva');
    expect($titles)->not->toContain('Hecha');
    expect($titles)->not->toContain('Archivada');
    expect($titles)->not->toContain('En pool');
    expect($props['candidates'][0]['id'])->toBe($vieja->id);
    expect($props['candidates'][1]['id'])->toBe($nueva->id);
});

test('week candidates respeta el limite de 20', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ProjectTask::factory()
        ->count(22)
        ->create(['user_id' => $user->id, 'project_id' => null, 'in_week' => false, 'is_done' => false]);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];

    expect($props['candidates'])->toHaveCount(20);
});

test('week candidates excluye tareas de otro usuario', function () {
    $otro = User::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'Mia']);
    ProjectTask::factory()->create(['user_id' => $otro->id, 'project_id' => null, 'title' => 'Ajena']);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($titles)->toContain('Mia');
    expect($titles)->not->toContain('Ajena');
});
