<?php

use App\Models\JournalDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('muestra el día local del usuario aunque en UTC ya sea mañana', function () {
    Carbon::setTestNow('2026-11-16 02:30:00'); // 23:30 del 15 en Buenos Aires

    $this->get('/diario')->assertOk()->assertInertia(fn ($page) => $page
        ->component('journal/Index')
        ->where('date', '2026-11-15')
        ->where('today', '2026-11-15'));
});

it('crea la entrada del día y la actualiza sin duplicar', function () {
    $this->put('/diario/2026-11-15', [
        'body' => 'Primer día',
        'mood' => 4,
        'tags' => ['Casa', ' casa ', 'viaje'],
        'is_private' => false,
    ])->assertRedirect();

    $this->put('/diario/2026-11-15', ['body' => 'Corregido', 'mood' => 5, 'tags' => [], 'is_private' => false])
        ->assertRedirect();

    $day = JournalDay::firstOrFail();

    expect(JournalDay::count())->toBe(1)
        ->and($day->body)->toBe('Corregido')
        ->and($day->mood)->toBe(5);
});

it('normaliza y deduplica etiquetas', function () {
    $this->put('/diario/2026-11-15', ['body' => 'x', 'tags' => [' Viaje ', 'viaje', 'CASA', '', 'a', 'b', 'c', 'd', 'e', 'f', 'g'], 'is_private' => false]);

    expect(JournalDay::firstOrFail()->tags)->toBe(['viaje', 'casa', 'a', 'b', 'c', 'd', 'e', 'f']);
});

it('borra el día cuando se vacía por completo', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'algo', 'mood' => 3, 'tags' => ['x']]);

    $this->put('/diario/2026-11-10', ['body' => '', 'mood' => null, 'tags' => [], 'is_private' => false])
        ->assertRedirect();

    expect(JournalDay::whereKey($day->id)->exists())->toBeFalse();
});

it('no muestra el día de otro usuario', function () {
    JournalDay::factory()->create(['date' => '2026-11-09', 'body' => 'ajeno']);

    $this->get('/diario/2026-11-09')->assertOk()
        ->assertInertia(fn ($page) => $page->where('day', null));
});

it('rechaza un ánimo fuera de rango y un cuerpo gigante', function () {
    $this->put('/diario/2026-11-08', ['mood' => 9, 'is_private' => false])
        ->assertSessionHasErrors('mood');

    $this->put('/diario/2026-11-08', ['body' => str_repeat('a', 20001), 'is_private' => false])
        ->assertSessionHasErrors('body');
});

it('exige autenticación', function () {
    auth()->logout();

    $this->get('/diario')->assertRedirect('/login');
});
