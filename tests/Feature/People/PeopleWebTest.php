<?php

use App\Models\Person;
use App\Models\PersonInteraction;
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
    ])->assertRedirect(route('people.show', $person));

    expect($person->fresh()->first_name)->toBe('Ana Renombrada');

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
