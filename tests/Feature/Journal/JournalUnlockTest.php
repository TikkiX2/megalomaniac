<?php

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
