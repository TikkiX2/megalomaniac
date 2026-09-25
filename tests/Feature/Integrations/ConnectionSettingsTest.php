<?php

use App\Integrations\ConnectorRegistry;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FakeConnector;

beforeEach(function () {
    FakeConnector::reset();
    $this->withoutVite();

    app()->instance(ConnectorRegistry::class, new ConnectorRegistry([FakeConnector::class]));
});

it('renders the connections page with catalog and own connections only', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);
    Connection::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/connections')
            ->has('connections', 1)
            ->where('connections.0.name', 'Mi fake')
            ->has('catalog', 1)
            ->where('catalog.0.kind', 'fake')
            ->where('catalog.0.group', 'Test')
            ->has('catalog.0.auth_fields')
            ->has('catalog.0.transports'));
});

it('stores a connection with encrypted credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('connections.store'), [
        'kind' => 'fake',
        'name' => 'Mi fake',
        'auth_type' => 'api_token',
        'credentials' => ['token' => 'secret-token'],
        'base_url' => 'https://api.example.com',
        'transport' => 'direct',
        'enabled' => true,
    ])->assertRedirect(route('connections.index'));

    $connection = Connection::firstOrFail();

    expect($connection->credentials['token'])->toBe('secret-token')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('secret-token')
        ->and($connection->user_id)->toBe($user->id);
});

it('rejects invalid store payloads', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('connections.store'), ['kind' => 'nope', 'name' => ''])
        ->assertSessionHasErrors(['kind', 'name', 'auth_type', 'transport', 'enabled']);
});

it('accepts an empty base url from the wizard', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('connections.store'), [
            'kind' => 'fake',
            'name' => 'Sin URL',
            'auth_type' => 'api_token',
            'credentials' => ['token' => 'x'],
            'base_url' => '',
            'transport' => 'direct',
            'transport_config' => [],
            'enabled' => true,
        ])
        ->assertRedirect(route('connections.index'));

    expect(Connection::where('name', 'Sin URL')->first()->base_url)->toBeNull();
});

it('keeps credentials when update sends none', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create([
        'kind' => 'fake',
        'credentials' => ['token' => 'keep-me'],
    ]);

    $this->actingAs($user)->patch(route('connections.update', $connection), [
        'name' => 'Renombrada',
        'credentials' => [],
    ])->assertRedirect();

    expect($connection->fresh()->name)->toBe('Renombrada')
        ->and($connection->fresh()->credentials['token'])->toBe('keep-me');
});

it('toggles enabled through update', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake', 'enabled' => true]);

    $this->actingAs($user)->patch(route('connections.update', $connection), [
        'enabled' => false,
    ])->assertRedirect();

    expect($connection->fresh()->enabled)->toBeFalse();
});

it('forbids acting on foreign connections', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $this->actingAs(User::factory()->create())
        ->patch(route('connections.update', $connection), ['name' => 'X'])
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->delete(route('connections.destroy', $connection))
        ->assertNotFound();
});

it('deletes an own connection', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);

    $this->actingAs($user)->delete(route('connections.destroy', $connection))->assertRedirect();

    expect(Connection::find($connection->id))->toBeNull();
});

it('tests a saved connection through the executor', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);

    $this->actingAs($user)
        ->post(route('connections.test'), ['connection_id' => $connection->id])
        ->assertRedirect()
        ->assertSessionHas('test_result', fn (array $result) => $result['ok'] === true);

    expect($connection->fresh()->last_tested_at)->not->toBeNull();
});

it('tests a draft connection without persisting it', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('connections.test'), [
            'kind' => 'fake',
            'auth_type' => 'api_token',
            'credentials' => ['token' => 'x'],
            'transport' => 'direct',
        ])
        ->assertRedirect()
        ->assertSessionHas('test_result', fn (array $result) => $result['ok'] === true);

    expect(Connection::count())->toBe(0);
});

it('exposes the actions of a connection as json', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);

    $this->actingAs($user)
        ->get(route('connections.actions', $connection))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->has('actions', 2)
            ->where('actions.0.key', 'ping')
            ->etc());
});
