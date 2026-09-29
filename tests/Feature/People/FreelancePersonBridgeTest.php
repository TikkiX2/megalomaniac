<?php

use App\Models\Client;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('links a client to a person without duplicating data', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);

    $this->post('/freelance/clients', [
        'name' => 'Estudio Gómez',
        'person_id' => $person->id,
    ])->assertRedirect(route('freelance.clients.index'));

    $client = Client::where('name', 'Estudio Gómez')->firstOrFail();

    expect($client->person_id)->toBe($person->id)
        ->and($client->person->first_name)->toBe('Ana')
        ->and($person->clients()->count())->toBe(1);
});

it('rejects a person owned by another user', function () {
    $intruder = Person::factory()->create();

    $this->post('/freelance/clients', [
        'name' => 'Estudio Ajeno',
        'person_id' => $intruder->id,
    ])->assertSessionHasErrors('person_id');
});
