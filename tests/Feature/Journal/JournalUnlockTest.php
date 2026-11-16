<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('rechaza una contraseña incorrecta', function () {
    $this->post('/diario/desbloquear', ['password' => 'mala'])
        ->assertSessionHasErrors('password');

    expect(session('journal_unlocked_until'))->toBeNull();
});

it('desbloquea por 15 minutos con la contraseña correcta', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    $this->post('/diario/desbloquear', ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(session('journal_unlocked_until'))->toBe(now()->addSeconds(900)->timestamp);
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', false));
});

it('vuelve a bloquear a los 900 segundos', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    $this->post('/diario/desbloquear', ['password' => 'password']);

    Carbon::setTestNow('2026-11-16 10:14:59');
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', false));

    Carbon::setTestNow('2026-11-16 10:15:01');
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', true));
});

it('limita a cinco intentos por minuto', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/diario/desbloquear', ['password' => 'mala'])->assertSessionHasErrors('password');
    }

    $this->post('/diario/desbloquear', ['password' => 'mala'])->assertStatus(429);
});

it('el logout vuelve a bloquear', function () {
    $this->post('/diario/desbloquear', ['password' => 'password']);
    $this->post('/logout');

    expect(session('journal_unlocked_until'))->toBeNull();
});

it('redirige al login si no hay sesión', function () {
    auth()->logout();

    $this->post('/diario/desbloquear', ['password' => 'password'])->assertRedirect('/login');
});

it('marca has_private cuando el día es privado', function () {
    JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-10',
        'is_private' => true,
    ]);

    $this->get('/diario/2026-11-10')->assertInertia(fn ($page) => $page
        ->where('has_private', true)
        ->where('locked', true));
});

it('marca has_private cuando hay un momento privado', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-11',
        'is_private' => false,
    ]);

    JournalMoment::factory()->for($this->user)->create([
        'journal_day_id' => $day->id,
        'occurred_at' => '2026-11-11 09:00:00',
        'is_private' => true,
    ]);

    $this->get('/diario/2026-11-11')->assertInertia(fn ($page) => $page
        ->where('has_private', true));
});

it('deja has_private en falso con contenido público', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-12',
        'is_private' => false,
    ]);

    JournalMoment::factory()->for($this->user)->create([
        'journal_day_id' => $day->id,
        'occurred_at' => '2026-11-12 09:00:00',
        'is_private' => false,
    ]);

    $this->get('/diario/2026-11-12')->assertInertia(fn ($page) => $page
        ->where('has_private', false));
});

it('no cuenta contenido privado de otro usuario', function () {
    JournalDay::factory()->create([
        'date' => '2026-11-13',
        'is_private' => true,
    ]);

    $this->get('/diario/2026-11-13')->assertInertia(fn ($page) => $page
        ->where('has_private', false));
});
