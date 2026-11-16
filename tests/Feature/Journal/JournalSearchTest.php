<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('busca en días y momentos públicos', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'Caminata en la costanera']);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'title' => 'Costanera al atardecer', 'body' => 'foto']);

    $this->get('/diario/buscar?q=costanera')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->has('days', 1)
            ->has('moments', 1));
});

it('no devuelve memorias privadas', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'Secreto de costanera', 'is_private' => true]);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'costanera privada', 'is_private' => true]);

    $this->get('/diario/buscar?q=costanera')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', [])->where('moments', []));
});

it('busca sin distinguir mayúsculas', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'caminata en la costanera']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-02', 'body' => 'árbol añoso']);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'title' => 'Costanera al atardecer', 'body' => 'foto']);

    $this->get('/diario/buscar?q=Caminata')->assertOk()
        ->assertInertia(fn ($page) => $page->has('days', 1)->has('moments', 0));

    $this->get('/diario/buscar?q=COSTANERA')->assertOk()
        ->assertInertia(fn ($page) => $page->has('days', 1)->has('moments', 1));

    $this->get('/diario/buscar?'.http_build_query(['q' => 'ÁRBOL']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('days', 1));
});

it('ignora una búsqueda con forma de arreglo', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'algo']);

    $this->get('/diario/buscar?q[]=x')->assertOk()
        ->assertInertia(fn ($page) => $page->where('q', '')->where('days', []));
});

it('ignora búsquedas de menos de dos caracteres y escapa comodines', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => '100% real']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-02', 'body' => 'abc']);

    $this->get('/diario/buscar?q=a')->assertOk()->assertInertia(fn ($page) => $page->where('days', []));
    $this->get('/diario/buscar?q=a%25')->assertOk()->assertInertia(fn ($page) => $page->where('days', []));
    $this->get('/diario/buscar?q=100%25')->assertOk()->assertInertia(fn ($page) => $page->has('days', 1));
});
