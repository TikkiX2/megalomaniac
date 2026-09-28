<?php

use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function mcpOAuthDiscovery(): void
{
    Http::fake([
        '*oauth-protected-resource*' => Http::response([
            'resource' => 'https://mcp.test/mcp',
            'authorization_servers' => ['https://mcp.test'],
        ], 200),
        '*oauth-authorization-server*' => Http::response([
            'issuer' => 'https://mcp.test',
            'authorization_endpoint' => 'https://mcp.test/authorize',
            'token_endpoint' => 'https://mcp.test/token',
            'registration_endpoint' => 'https://mcp.test/register',
        ], 200),
        '*register' => Http::response(['client_id' => 'dcr-client', 'client_secret' => 'dcr-secret'], 201),
        '*token*' => Http::response(['access_token' => 'oauth-at', 'refresh_token' => 'oauth-rt', 'expires_in' => 3600], 200),
    ]);
}

function mcpOAuthConnection(User $user): Connection
{
    return Connection::factory()->for($user)->create([
        'kind' => 'mcp',
        'base_url' => 'https://mcp.test/mcp',
        'auth_type' => 'oauth2',
        'credentials' => [],
        'options' => [],
    ]);
}

it('redirects to the authorization server with pkce and dynamic registration', function () {
    mcpOAuthDiscovery();

    $user = User::factory()->create();
    $connection = mcpOAuthConnection($user);

    $response = $this->actingAs($user)->get(route('integrations.mcp.connect', $connection));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('https://mcp.test/authorize')
        ->and($location)->toContain('code_challenge=')
        ->and($location)->toContain('code_challenge_method=S256')
        ->and($location)->toContain('state=')
        ->and($location)->toContain('client_id=dcr-client');
});

it('exchanges the callback code and stores encrypted tokens', function () {
    mcpOAuthDiscovery();

    $user = User::factory()->create();
    $connection = mcpOAuthConnection($user);

    $connect = $this->actingAs($user)->get(route('integrations.mcp.connect', $connection));
    parse_str(parse_url((string) $connect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($user)
        ->get(route('integrations.mcp.callback', $connection).'?code=the-code&state='.$query['state'])
        ->assertRedirect(route('connections.index'));

    $connection->refresh();

    expect($connection->credentials['access_token'])->toBe('oauth-at')
        ->and($connection->credentials['refresh_token'])->toBe('oauth-rt')
        ->and($connection->credentials['client_id'])->toBe('dcr-client')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('oauth-at');
});

it('flashes an error when oauth fails', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $user = User::factory()->create();
    $connection = mcpOAuthConnection($user);

    $this->actingAs($user)
        ->get(route('integrations.mcp.connect', $connection))
        ->assertRedirect(route('connections.index'))
        ->assertSessionHas('error');
});

it('404s foreign mcp connections', function () {
    $connection = mcpOAuthConnection(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('integrations.mcp.connect', $connection))
        ->assertNotFound();
});
