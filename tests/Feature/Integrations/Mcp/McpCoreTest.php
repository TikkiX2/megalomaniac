<?php

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Mcp\McpClientFactory;
use App\Integrations\Mcp\McpDiscoveryService;
use App\Integrations\Mcp\McpTokenStore;
use App\Integrations\Mcp\McpToolMapper;
use App\Models\Connection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Client\OAuth\TokenSet;
use Laravel\Mcp\Client\Primitives\Tool;
use Tests\Support\McpFake;

it('maps mcp tools to actions with schema and annotations', function () {
    $tool = Tool::from(null, [
        'name' => 'search',
        'title' => 'Buscar',
        'description' => 'Busca cosas',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Texto'],
                'limit' => ['type' => 'integer', 'default' => 10],
                'tags' => ['type' => 'array'],
                'mode' => ['type' => 'string', 'enum' => ['fast', 'full']],
            ],
            'required' => ['query'],
        ],
        'annotations' => ['readOnlyHint' => true],
    ]);

    $action = (new McpToolMapper)->toAction($tool);

    expect($action->key)->toBe('tools.search')
        ->and($action->label)->toBe('Buscar')
        ->and($action->access)->toBe(ActionAccess::Read)
        ->and($action->params)->toHaveCount(4)
        ->and($action->params[0]->name)->toBe('query')
        ->and($action->params[0]->required)->toBeTrue()
        ->and($action->params[1]->type)->toBe('integer')
        ->and($action->params[1]->default)->toBe(10)
        ->and($action->params[2]->type)->toBe('array')
        ->and($action->params[3]->enum)->toBe(['fast', 'full']);
});

it('defaults unknown mcp tools to write and destructive hints to destructive', function () {
    $mapper = new McpToolMapper;

    $write = $mapper->toAction(Tool::from(null, ['name' => 'create_page', 'inputSchema' => []]));
    $destructive = $mapper->toAction(Tool::from(null, ['name' => 'delete_page', 'inputSchema' => [], 'annotations' => ['destructiveHint' => true]]));

    expect($write->access)->toBe(ActionAccess::Write)
        ->and($destructive->access)->toBe(ActionAccess::Destructive);
});

it('builds clients with token, headers and timeout', function () {
    McpFake::server();

    $withToken = McpFake::connection(['credentials' => ['token' => 'mcp-token']]);
    (new McpClientFactory)->for($withToken)->tools();

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer mcp-token'));

    Http::fake();
    (new McpClientFactory)->base(McpFake::connection())->tools();

    Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
});

it('stores oauth tokens encrypted and refreshes them when expired', function () {
    $connection = Connection::factory()->create([
        'kind' => 'mcp',
        'base_url' => 'https://mcp.test/mcp',
        'auth_type' => 'oauth2',
        'credentials' => [],
        'options' => [],
    ]);

    $store = app(McpTokenStore::class);
    $store->store($connection, new TokenSet(
        accessToken: 'at-1',
        refreshToken: 'rt-1',
        expiresAt: time() + 3600,
        clientId: 'client-1',
        clientSecret: 'secret-1',
    ));

    $connection->refresh();

    expect($connection->credentials['access_token'])->toBe('at-1')
        ->and($connection->credentials['client_id'])->toBe('client-1')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('at-1');

    $connection->forceFill(['credentials' => array_merge($connection->credentials, [
        'expires_at' => now()->subMinute()->toIso8601String(),
    ])])->save();

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'oauth-protected-resource')) {
            return Http::response(['resource' => 'https://mcp.test/mcp', 'authorization_servers' => ['https://mcp.test']], 200);
        }

        if (str_contains($url, 'oauth-authorization-server')) {
            return Http::response([
                'issuer' => 'https://mcp.test',
                'authorization_endpoint' => 'https://mcp.test/authorize',
                'token_endpoint' => 'https://mcp.test/token',
            ], 200);
        }

        return Http::response(['access_token' => 'at-2', 'refresh_token' => 'rt-1', 'expires_in' => 3600], 200);
    });

    $refreshed = $store->ensureFresh($connection->refresh());

    expect($refreshed->credentials['access_token'])->toBe('at-2')
        ->and(now()->lt(Carbon::parse($refreshed->credentials['expires_at'])))->toBeTrue();
});

it('caches discovered tools until forgotten', function () {
    McpFake::server([['name' => 'ping', 'description' => 'Ping', 'inputSchema' => []]]);

    $connection = Connection::factory()->create([
        'kind' => 'mcp',
        'base_url' => 'https://mcp.test/mcp',
        'credentials' => [],
        'options' => [],
    ]);

    $service = app(McpDiscoveryService::class);

    $first = $service->tools($connection);
    $second = $service->tools($connection);

    expect($first)->toHaveCount(1)->and($second)->toHaveCount(1);

    $listCalls = collect(Http::recorded())
        ->filter(fn (array $pair): bool => ($pair[0]->data()['method'] ?? null) === 'tools/list')
        ->count();

    expect($listCalls)->toBe(1);

    $service->forget($connection);
    $service->tools($connection);

    $afterForget = collect(Http::recorded())
        ->filter(fn (array $pair): bool => ($pair[0]->data()['method'] ?? null) === 'tools/list')
        ->count();

    expect($afterForget)->toBe(2);
});
