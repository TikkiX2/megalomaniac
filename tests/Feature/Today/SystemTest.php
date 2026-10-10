<?php

use App\Models\Block;
use App\Models\Day;
use App\Models\DayItem;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Notifications\ChooseTomorrowNotification;
use Illuminate\Support\Facades\Notification;

test('run de bulk archive sin confirmado no archiva y deshacer restaura', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(3)->create(['user_id' => $user->id, 'is_done' => false, 'created_at' => now()->subDays(40)]);

    $this->post('/today/bulk-archive/run', ['filter' => 'older_than', 'days' => 30])->assertInvalid(['confirmed']);
    expect(ProjectTask::notArchived()->count())->toBe(3);

    $this->post('/today/bulk-archive/run', ['filter' => 'older_than', 'days' => 30, 'confirmed' => true])->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(3);

    $props = $this->get('/today/archived')->assertOk()->viewData('page')['props'];
    expect($props['undoable'])->toBe(3);

    $this->post('/today/archived/undo')->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(0);

    $props = $this->get('/today/archived')->assertOk()->viewData('page')['props'];
    expect($props['undoable'])->toBe(0);
});

test('filtros bulk archive cubren project y all', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $project = Project::factory()->create(['user_id' => $user->id]);
    $enProyecto = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => $project->id, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null, 'is_done' => false]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => true]);

    // filter=nunca: no archiva nada.
    $this->post('/today/bulk-archive/run', ['filter' => 'nunca', 'confirmed' => true])->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(0);

    $this->post('/today/bulk-archive/run', ['filter' => 'project', 'project_id' => $project->id, 'confirmed' => true])->assertRedirect();
    expect(ProjectTask::archived()->pluck('id')->all())->toBe([$enProyecto->id]);

    $this->post('/today/bulk-archive/run', ['filter' => 'all', 'confirmed' => true])->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(2);
    expect(ProjectTask::archived()->where('is_done', true)->count())->toBe(0);
});

test('notify usa texto neutro sin culpa y cierra sin mutar', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->artisan('today:notify')->assertSuccessful();
    Notification::assertSentTo($user, ChooseTomorrowNotification::class);

    Notification::assertSentTo($user, function (ChooseTomorrowNotification $notification) use ($user) {
        $data = $notification->toDatabase($user);

        expect($data['title'])->toContain('¿Elegimos las 3 de mañana?');
        expect($data['body'])->toContain('dejarlo vacío');
        expect($data['url'])->toBe('/today/tomorrow');

        $text = strtolower($data['title'].' '.$data['body']);
        expect($text)->not->toMatch('/fallaste|lástima|otra vez|perdiste|racha|deberías/');

        return true;
    });

    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::factory()->create(['day_id' => $day->id, 'state' => 'pending']);
    $this->artisan('today:close')->assertSuccessful();
    expect($item->fresh()->state)->toBe('pending');
});

test('bloques CRUD', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/today/blocks', ['label' => 'Proyecto', 'weekday' => 3, 'start_time' => '19:00', 'duration_min' => 90])->assertRedirect();
    expect(Block::count())->toBe(1);

    $block = Block::first();
    $this->patch("/today/blocks/{$block->id}", ['label' => 'Gimnasio', 'duration_min' => 60])->assertRedirect();
    expect($block->fresh()->label)->toBe('Gimnasio');
    expect($block->fresh()->duration_min)->toBe(60);

    $props = $this->get('/today/week')->assertOk()->viewData('page')['props'];
    expect(collect($props['blocks'])->pluck('label')->all())->toContain('Gimnasio');

    $this->delete("/today/blocks/{$block->id}")->assertRedirect();
    expect(Block::count())->toBe(0);
});

test('bloques validan campos y solo el dueño puede editar', function () {
    $owner = User::factory()->create();
    $block = Block::factory()->create(['user_id' => $owner->id]);

    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post('/today/blocks', ['label' => '', 'weekday' => 9, 'start_time' => '19:00', 'duration_min' => 1])
        ->assertInvalid(['label', 'weekday', 'duration_min']);

    $this->patch("/today/blocks/{$block->id}", ['label' => 'Ajeno'])->assertNotFound();
    $this->delete("/today/blocks/{$block->id}")->assertNotFound();
    expect($block->fresh()->label)->not->toBe('Ajeno');
});
