<?php

use App\Models\Block;
use App\Models\Day;
use App\Models\Food;
use App\Models\MealLog;
use App\Models\QueueItem;
use App\Models\Routine;
use App\Models\User;
use App\Services\Media\DailyPickService;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('uses the user local day on the today dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 01:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 01:00 UTC del 10/11 == 22:00 local del 9/11.
    $this->actingAs($user)->get('/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('date', '2026-11-09'));
});

it('counts meal logs from the local day on the today dashboard', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 01:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $food = Food::create([
        'name' => 'Arroz',
        'calories' => 500,
        'protein' => 10,
        'carbs' => 100,
        'fats' => 2,
    ]);
    $log = MealLog::create([
        'user_id' => $user->id,
        'date' => '2026-11-09',
        'meal_type' => 'lunch',
    ]);
    $log->items()->create([
        'food_id' => $food->id,
        'quantity' => 1,
        'calories_snapshot' => 500,
        'protein_snapshot' => 10,
        'carbs_snapshot' => 100,
        'fats_snapshot' => 2,
    ]);

    $this->actingAs($user)->get('/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('caloriesToday', 500));
});

it('resolves today and tomorrow with the local clock', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 06:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 06:00 UTC del 10/11 == 03:00 local del 10/11: todavía es "hoy".
    $this->actingAs($user)->get('/today/tomorrow')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('target', 'today')
            ->where('fecha', '2026-11-10'));
});

it('builds tomorrow from the local date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 02:00 UTC del 10/11 == 23:00 local del 9/11: mañana local es el 10/11.
    $this->actingAs($user)->get('/today/tomorrow?target=tomorrow')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('fecha', '2026-11-10'));
});

it('stores the today plan on the local date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    $this->actingAs($user)->post('/today/tomorrow', [
        'target' => 'today',
        'items' => [],
    ])->assertRedirect(route('dashboard'));

    expect(Day::where('user_id', $user->id)->whereDate('date', '2026-11-09')->exists())->toBeTrue();
});

it('puts an item into the local today', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $day = Day::create(['user_id' => $user->id, 'date' => '2026-11-10']);
    $item = $day->items()->create([
        'title' => 'Tarea',
        'anchor' => 'wake_up',
        'position' => 1,
        'state' => 'pending',
    ]);

    $this->actingAs($user)->post("/today/tomorrow/{$item->id}/put-today")
        ->assertRedirect();

    expect(Day::where('user_id', $user->id)->whereDate('date', '2026-11-09')->exists())->toBeTrue();
});

it('shows pending items from the local yesterday', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $yesterday = Day::create(['user_id' => $user->id, 'date' => '2026-11-08']);
    $yesterday->items()->create([
        'title' => 'Pendiente de ayer',
        'anchor' => 'wake_up',
        'position' => 1,
        'state' => 'pending',
    ]);

    $this->actingAs($user)->get('/today/tomorrow?target=tomorrow')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pendientesAyer', 1)
            ->where('pendientesAyer.0.title', 'Pendiente de ayer'));
});

it('picks the surprise item for the local date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $items = QueueItem::factory()->count(3)->create([
        'user_id' => $user->id,
        'type' => 'pelicula',
    ]);
    $expected = $items->first();

    $this->mock(DailyPickService::class, function ($mock) use ($user, $expected) {
        $mock->shouldReceive('pick')
            ->atLeast()->once()
            ->with($user->id, '2026-11-09', 'pelicula', Mockery::any())
            ->andReturn($expected);
    });

    $this->actingAs($user)->getJson('/today/surprise?type=pelicula')
        ->assertOk()
        ->assertJsonPath('title', $expected->title);
});

it('uses the local weekday for the dashboard block and routine', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    Block::factory()->create(['user_id' => $user->id, 'weekday' => 1, 'active' => true]);
    Routine::factory()->create([
        'user_id' => $user->id,
        'name' => 'Empuje',
        'scheduled_date' => 'Monday',
    ]);

    // 02:00 UTC del 10/11 == 23:00 local del lunes 9/11.
    $this->actingAs($user)->get('/today')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('block.weekday', 1)
            ->where('routine.name', 'Empuje'));
});

it('creates the pick type day on the local date', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-10 02:00:00', 'UTC'));

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    $this->actingAs($user)->post('/today/pick-type', ['type' => 'serie'])
        ->assertRedirect();

    expect(Day::where('user_id', $user->id)->whereDate('date', '2026-11-09')->value('pick_type'))->toBe('serie');
});
