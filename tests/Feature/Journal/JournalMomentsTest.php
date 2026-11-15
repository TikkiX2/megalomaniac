<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 18:00:00');

    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('crea un momento de hoy con la hora actual', function () {
    Carbon::setTestNow('2026-11-15 18:00:00');

    $this->post('/diario/2026-11-15/momentos', ['title' => 'Café', 'body' => 'Con Ana', 'tags' => ['tarde']])
        ->assertRedirect();

    $moment = JournalMoment::with('day')->firstOrFail();

    expect($moment->title)->toBe('Café')
        ->and($moment->day->date->toDateString())->toBe('2026-11-15')
        ->and($moment->occurred_at->utc()->toDateTimeString())->toBe('2026-11-15 18:00:00');
});

it('crea un momento en un día pasado a las 12 locales', function () {
    $this->post('/diario/2026-11-01/momentos', ['body' => 'Recuerdo viejo'])->assertRedirect();

    $moment = JournalMoment::firstOrFail();

    expect($moment->occurred_at->setTimezone('America/Argentina/Buenos_Aires')->format('H:i'))->toBe('12:00');
});

it('re-ancla el momento cuando cambia de fecha y poda el día viejo', function () {
    $this->post('/diario/2026-11-01/momentos', ['body' => 'x'])->assertRedirect();
    $moment = JournalMoment::firstOrFail();
    $oldDay = $moment->journal_day_id;

    $this->patch("/diario/momentos/{$moment->id}", ['occurred_at' => '2026-11-03T09:00:00-03:00'])
        ->assertRedirect();

    expect($moment->fresh()->day->date->toDateString())->toBe('2026-11-03')
        ->and(JournalDay::whereKey($oldDay)->exists())->toBeFalse();
});

it('borra el último momento y con él el día vacío', function () {
    $this->post('/diario/2026-11-02/momentos', ['body' => 'único'])->assertRedirect();
    $moment = JournalMoment::firstOrFail();
    $dayId = $moment->journal_day_id;

    $this->delete("/diario/momentos/{$moment->id}")->assertRedirect();

    expect(JournalMoment::count())->toBe(0)
        ->and(JournalDay::whereKey($dayId)->exists())->toBeFalse();
});

it('devuelve 404 en momentos ajenos', function () {
    $moment = JournalMoment::factory()->create();

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'hack'])->assertNotFound();
    $this->delete("/diario/momentos/{$moment->id}")->assertNotFound();
});

it('valida cuerpo requerido y largo del título', function () {
    $this->post('/diario/2026-11-04/momentos', ['body' => ''])->assertSessionHasErrors('body');
    $this->post('/diario/2026-11-04/momentos', ['body' => 'x', 'title' => str_repeat('a', 121)])->assertSessionHasErrors('title');
});
