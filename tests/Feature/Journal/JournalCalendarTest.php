<?php

use App\Models\JournalDay;
use App\Models\User;
use App\Services\Journal\JournalStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('marca el mes con contenido y distingue sólo privado', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-03', 'body' => 'público']);
    $privado = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-04', 'body' => 'íntimo', 'is_private' => true]);

    $this->get('/diario/calendario?month=2026-11')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/Calendar')
            ->where('days.0.public', true)
            ->where('days.1.public', false)
            ->where('days.1.date', '2026-11-04'));
});

it('no filtra el mes de otro usuario', function () {
    JournalDay::factory()->create(['date' => '2026-11-05']);

    $this->get('/diario/calendario?month=2026-11')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', []));
});

it('cuenta racha con hoy, o con ayer si hoy aún no hay nada', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    foreach (['2026-11-13', '2026-11-14'] as $date) {
        JournalDay::factory()->for($this->user)->create(['date' => $date, 'mood' => 3]);
    }

    expect(app(JournalStats::class)->streak($this->user))->toBe(2);

    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-15', 'mood' => 4]);
    expect(app(JournalStats::class)->streak($this->user))->toBe(3);
});

it('corta la racha con dos días sin contenido', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-12', 'mood' => 3]);

    expect(app(JournalStats::class)->streak($this->user))->toBe(0);
});

it('incluye días que sólo tienen momentos', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-20',
        'body' => null,
        'mood' => null,
        'is_private' => true,
    ]);

    $day->moments()->create([
        'user_id' => $this->user->id,
        'body' => 'Una cena',
        'occurred_at' => '2026-11-20 23:00:00',
        'is_private' => false,
    ]);

    $this->get('/diario/calendario?month=2026-11')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('days', fn ($days) => collect($days)->contains(
                fn ($item) => $item['date'] === '2026-11-20'
                    && $item['moments'] === 1
                    && $item['public'] === true
            )));
});

it('cae al mes y al día locales con un mes inválido', function () {
    // 02:00 UTC del 15 son las 23:00 del 14 en Buenos Aires.
    Carbon::setTestNow('2026-11-15 02:00:00');

    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-14', 'mood' => 3]);

    $this->get('/diario/calendario?month=2026-13')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('month', '2026-11')
            ->where('today', '2026-11-14')
            ->where('streak', 1)
            ->where('days', fn ($days) => collect($days)->contains('date', '2026-11-14'))
            ->where('prevMonth', '2026-10')
            ->where('nextMonth', '2026-12'));

    $this->get('/diario/calendario')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('month', '2026-11')
            ->where('today', '2026-11-14'));
});

it('cuenta la racha desde el día local aunque en UTC ya sea mañana', function () {
    // 02:00 UTC del 15 son las 23:00 del 14 en Buenos Aires.
    Carbon::setTestNow('2026-11-15 02:00:00');

    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-13', 'mood' => 3]);

    expect(app(JournalStats::class)->streak($this->user))->toBe(1);
});

it('pagina el mes pedido', function () {
    $this->get('/diario/calendario?month=2026-01')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('month', '2026-01')
            ->where('prevMonth', '2025-12')
            ->where('nextMonth', '2026-02'));
});

it('lleva el riel del mes actual a la página del día', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-14', 'mood' => 3]);

    $this->get('/diario/2026-11-15')->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('journal/Index')
            ->where('calendar.month', '2026-11')
            ->where('calendar.streak', 1)
            ->where('calendar.days', fn ($days) => collect($days)->contains('date', '2026-11-14')));
});
