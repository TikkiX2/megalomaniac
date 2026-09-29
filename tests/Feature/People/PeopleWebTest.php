<?php

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\Models\User;
use App\People\Enums\Closeness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('lists only the authenticated user people', function () {
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    Person::factory()->create(['first_name' => 'Intruso']);
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Archivada', 'is_archived' => true]);

    $this->get('/people')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Index')
            ->where('people.data', fn ($rows) => collect($rows)->pluck('first_name')->contains('Ana'))
            ->where('people.data', fn ($rows) => ! collect($rows)->pluck('first_name')->contains('Intruso'))
            ->where('people.data', fn ($rows) => ! collect($rows)->pluck('first_name')->contains('Archivada')));
});

it('filters people by search, closeness and stale days', function () {
    Person::factory()->create([
        'user_id' => $this->user->id,
        'first_name' => 'Ana',
        'closeness' => Closeness::Close,
        'last_contacted_at' => now()->subDays(90),
    ]);
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Bruno']);

    $this->get('/people?search=ana&closeness=close&stale_days=30')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('people.data', fn ($rows) => collect($rows)->pluck('first_name')->all() === ['Ana'])
            ->where('filters.stale_days', '30'));
});

it('shows a person with key dates, socials and interactions', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    PersonInteraction::factory()->create(['user_id' => $this->user->id, 'person_id' => $person->id]);

    $this->get("/people/{$person->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Show')
            ->where('person.first_name', 'Ana')
            ->has('interactions.data', 1));
});

it('forbids viewing another user person', function () {
    $person = Person::factory()->create();

    $this->get("/people/{$person->id}")->assertForbidden();
});

it('renders the global timeline', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    PersonInteraction::factory()->create(['user_id' => $this->user->id, 'person_id' => $person->id]);

    $this->get('/people/timeline')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('people/Timeline')->has('interactions.data', 1));
});

it('renders the person form', function () {
    $this->get('/people/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('people/Form'));
});

it('creates a person from the web form', function () {
    $this->post('/people', [
        'first_name' => 'Ana',
        'last_name' => 'Gómez',
        'closeness' => 'close',
        'is_favorite' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('people', [
        'first_name' => 'Ana',
        'user_id' => $this->user->id,
        'is_favorite' => true,
    ]);
});

it('updates and deletes a person', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->put("/people/{$person->id}", [
        'first_name' => 'Ana Renombrada',
        'closeness' => 'inner_circle',
        'address' => 'Av. Siempreviva 742',
    ])->assertRedirect(route('people.show', $person));

    expect($person->fresh()->first_name)->toBe('Ana Renombrada')
        ->and($person->fresh()->address)->toBe('Av. Siempreviva 742');

    $this->delete("/people/{$person->id}")->assertRedirect(route('people.index'));

    expect(Person::find($person->id))->toBeNull();
});

it('uploads an avatar through medialibrary', function () {
    Storage::fake('public');
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('ana.jpg'),
    ])->assertRedirect();

    expect($person->fresh()->getMedia('avatar'))->toHaveCount(1)
        ->and($person->fresh()->avatar_url)->not->toBeNull();
});

it('rejects an invalid payload', function () {
    $this->post('/people', ['first_name' => '', 'closeness' => 'nope'])
        ->assertSessionHasErrors(['first_name', 'closeness']);
});

it('logs an interaction and refreshes last_contacted_at', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $occurredAt = now()->subDay()->startOfMinute();

    $this->post("/people/{$person->id}/interactions", [
        'channel' => 'call',
        'occurred_at' => $occurredAt->toDateTimeString(),
        'title' => 'Llamada',
    ])->assertRedirect();

    $this->assertDatabaseHas('person_interactions', [
        'person_id' => $person->id,
        'channel' => 'call',
    ]);
    expect($person->fresh()->last_contacted_at->equalTo($occurredAt))->toBeTrue();
});

it('quick logs a contact with the default channel', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/contacted")->assertRedirect();

    $this->assertDatabaseHas('person_interactions', [
        'person_id' => $person->id,
        'channel' => 'message',
    ]);
});

it('deletes an interaction owned by the user', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $interaction = PersonInteraction::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => $person->id,
    ]);

    $this->delete("/people/interactions/{$interaction->id}")->assertRedirect();

    expect(PersonInteraction::find($interaction->id))->toBeNull();
});

it('creates, updates and deletes key dates', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/key-dates", [
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->addDays(10)->toDateString(),
        'is_recurring_annually' => true,
    ])->assertRedirect();

    $keyDate = $person->keyDates()->firstOrFail();

    $this->patch("/people/key-dates/{$keyDate->id}", ['remind_days_before' => 14])->assertRedirect();
    expect($keyDate->fresh()->remind_days_before)->toBe(14);

    $this->delete("/people/key-dates/{$keyDate->id}")->assertRedirect();
    expect(PersonKeyDate::find($keyDate->id))->toBeNull();
});

it('renders the key dates calendar', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'birthday' => now()->addDays(5)]);
    PersonKeyDate::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => $person->id,
        'type' => 'anniversary',
        'date' => now()->addDays(10)->toDateString(),
    ]);

    $archived = Person::factory()->create(['user_id' => $this->user->id, 'is_archived' => true]);
    PersonKeyDate::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => $archived->id,
        'type' => 'custom',
        'date' => now()->addDays(3)->toDateString(),
    ]);

    $this->get('/people/calendar')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Calendar')
            ->has('people', 1)
            ->has('keyDates', 1)
            ->where('keyDates.0.person_id', $person->id));
});

it('forbids touching key dates from another user', function () {
    $keyDate = PersonKeyDate::factory()->create();

    $this->patch("/people/key-dates/{$keyDate->id}", ['remind_days_before' => 14])->assertForbidden();
    $this->delete("/people/key-dates/{$keyDate->id}")->assertForbidden();

    expect($keyDate->fresh()->remind_days_before)->not->toBe(14);
});
