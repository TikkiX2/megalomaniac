<?php

use App\Models\JournalMoment;
use App\Models\Person;
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

it('enlaza y desenlaza personas del momento', function () {
    $ana = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    $this->post('/diario/2026-11-15/momentos', ['body' => 'Café', 'person_ids' => [$ana->id]])->assertRedirect();

    $moment = JournalMoment::firstOrFail();
    expect($moment->people->pluck('id')->all())->toBe([$ana->id]);

    $this->patch("/diario/momentos/{$moment->id}", ['person_ids' => []])->assertRedirect();
    expect($moment->fresh()->people)->toHaveCount(0);
});

it('rechaza personas de otro usuario', function () {
    $ajena = Person::factory()->create();

    $this->post('/diario/2026-11-15/momentos', ['body' => 'x', 'person_ids' => [$ajena->id]])
        ->assertSessionHasErrors('person_ids.0');
});

it('muestra los recuerdos vinculados en la ficha de la persona', function () {
    $ana = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'Cumple de Ana']);
    $moment->people()->attach($ana->id);

    $this->get("/people/{$ana->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('people/Show')
            ->has('memories', 1)
            ->where('memories.0.excerpt', 'Cumple de Ana'));
});
