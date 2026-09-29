<?php

use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api-token')->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer '.$this->token];
});

it('lists and searches people scoped to the token user', function () {
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    Person::factory()->create(['first_name' => 'Intruso']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/people?search=ana')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Ana');
});

it('creates a person and validates the payload', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/v1/people', ['first_name' => 'Ana', 'closeness' => 'close'])
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Ana');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/people', ['closeness' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'closeness']);
});

it('shows a person with key dates and socials', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $person->keyDates()->create(['user_id' => $this->user->id, 'type' => 'custom', 'date' => now()->toDateString()]);
    $person->socials()->create(['network' => 'instagram', 'handle' => '@ana']);

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/people/{$person->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.key_dates')
        ->assertJsonCount(1, 'data.socials');
});

it('updates, deletes and protects ownership', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $intruder = Person::factory()->create();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/people/{$person->id}", ['nickname' => 'Anita'])
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Anita');

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/people/{$intruder->id}", ['nickname' => 'hack'])
        ->assertForbidden();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/people/{$person->id}")
        ->assertOk();

    expect(Person::find($person->id))->toBeNull();
});

it('manages interactions, key dates and socials', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/interactions", [
            'channel' => 'call',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/people/{$person->id}/interactions")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/key-dates", [
            'type' => 'anniversary',
            'date' => now()->addDays(5)->toDateString(),
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/socials", ['network' => 'github', 'handle' => '@ana'])
        ->assertCreated();
});

it('allows patching a key date with only remind_days_before', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $keyDate = PersonKeyDate::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => $person->id,
    ]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/key-dates/{$keyDate->id}", ['remind_days_before' => 10])
        ->assertOk()
        ->assertJsonPath('data.remind_days_before', 10);
});

it('allows patching a social with only handle', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $social = PersonSocial::factory()->create(['person_id' => $person->id]);

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/socials/{$social->id}", ['handle' => '@updated'])
        ->assertOk()
        ->assertJsonPath('data.handle', '@updated');
});

it('returns upcoming key dates and birthdays', function () {
    $person = Person::factory()->create([
        'user_id' => $this->user->id,
        'birthday' => now()->addDays(4)->toDateString(),
    ]);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/people/upcoming?days=30')
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'birthday')
        ->assertJsonPath('data.0.person.id', $person->id);
});
