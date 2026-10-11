<?php

use App\Models\Project;
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

test('week pool y candidates incluyen proyecto con color', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $proyecto = Project::factory()->create(['user_id' => $user->id, 'name' => 'Con Color', 'type' => 'personal', 'color' => '#3B82F6']);

    $enPool = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => $proyecto->id, 'title' => 'EnPool', 'in_week' => true]);
    $candidata = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => $proyecto->id, 'title' => 'Candidata', 'in_week' => false]);
    $suelta = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'SinProyecto', 'in_week' => false]);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];

    $enPoolItem = collect($props['pool'])->firstWhere('id', $enPool->id);
    expect($enPoolItem['project'])->not->toBeNull();
    expect($enPoolItem['project']['id'])->toBe($proyecto->id);
    expect($enPoolItem['project']['name'])->toBe('Con Color');
    expect($enPoolItem['project']['color'])->toBe('#3B82F6');

    $candidataItem = collect($props['candidates'])->firstWhere('id', $candidata->id);
    expect($candidataItem['project']['id'])->toBe($proyecto->id);

    $sueltaItem = collect($props['candidates'])->firstWhere('id', $suelta->id);
    expect($sueltaItem['project'])->toBeNull();
});

test('week filtra candidates por cada dimension', function (string $dimension) {
    $user = User::factory()->create();
    $this->actingAs($user);

    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance', 'name' => 'Freelance']);
    $personal = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal', 'name' => 'Personal']);

    [$param, $valor, $coincide, $noCoincide] = match ($dimension) {
        'priority' => ['priority', 'High', ['priority' => 'High'], ['priority' => 'Low']],
        'status' => ['status', 'In Progress', ['status' => 'In Progress'], ['status' => 'Pending']],
        'tag' => ['tag', 'fase-a', ['tags' => ['fase-a']], ['tags' => ['fase-b']]],
        'type' => ['type', 'freelance', ['project_id' => $freelance->id], ['project_id' => $personal->id]],
        'project_id' => ['project_id', (string) $freelance->id, ['project_id' => $freelance->id], ['project_id' => $personal->id]],
    };

    $base = [
        'user_id' => $user->id,
        'project_id' => null,
        'priority' => 'Normal',
        'status' => 'Pending',
        'in_week' => false,
        'is_done' => false,
        'archived_at' => null,
    ];

    ProjectTask::factory()->create(array_merge($base, ['title' => 'Coincide'], $coincide));
    ProjectTask::factory()->create(array_merge($base, ['title' => 'No coincide'], $noCoincide));

    $props = $this->get('/today/week?'.$param.'='.urlencode($valor))->assertOk()->viewData('page')['props'];
    $titles = collect($props['candidates'])->pluck('title')->all();

    expect($titles)->toContain('Coincide');
    expect($titles)->not->toContain('No coincide');
})->with(['priority', 'status', 'tag', 'type', 'project_id']);

test('week limit 40 devuelve 40 candidatas', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ProjectTask::factory()->count(45)->create([
        'user_id' => $user->id,
        'project_id' => null,
        'in_week' => false,
        'is_done' => false,
    ]);

    $props = $this->get('/today/week?limit=40')->assertOk()->viewData('page')['props'];

    expect($props['limit'])->toBe(40);
    expect($props['candidates'])->toHaveCount(40);
    expect($props['hasMoreCandidates'])->toBeTrue();
});

test('week busqueda respeta filtros', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'foo Alta', 'priority' => 'Low', 'in_week' => false, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'foo Baja', 'priority' => 'High', 'in_week' => false, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'title' => 'bar Otra', 'priority' => 'Low', 'in_week' => false, 'is_done' => false]);

    $resultados = collect($this->getJson('/today/week/search?q=foo&priority=Low')->assertOk()->json());

    expect($resultados->pluck('title')->all())->toContain('foo Alta');
    expect($resultados->pluck('title')->all())->not->toContain('foo Baja', 'bar Otra');
});

test('week expone proyectos y etiquetas disponibles', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $otro = User::factory()->create();

    Project::factory()->create(['user_id' => $user->id, 'name' => 'Zeta', 'type' => 'personal']);
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Alfa', 'type' => 'freelance']);
    Project::factory()->create(['user_id' => $otro->id, 'name' => 'Ajeno']);
    $borrado = Project::factory()->create(['user_id' => $user->id, 'name' => 'Borrado']);
    $borrado->delete();

    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'tags' => ['zeta', 'alfa'], 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'tags' => ['beta'], 'is_done' => true]);
    ProjectTask::factory()->create(['user_id' => $otro->id, 'project_id' => null, 'tags' => ['ajeno'], 'is_done' => false]);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];

    expect(collect($props['projects'])->pluck('name')->all())->toBe(['Alfa', 'Zeta']);
    expect(collect($props['projects'])->first())->toHaveKeys(['id', 'name', 'type']);
    expect($props['availableTags'])->toBe(['alfa', 'zeta']);
    expect($props['filters'])->toBe([]);
    expect($props['limit'])->toBe(20);
    expect($props['hasMoreCandidates'])->toBeFalse();
});
