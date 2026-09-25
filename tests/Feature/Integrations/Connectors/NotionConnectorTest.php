<?php

use App\Integrations\Connectors\Notion\NotionConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function notion(): Connection
{
    return Connection::factory()->make([
        'kind' => 'notion',
        'base_url' => 'https://api.notion.com',
        'credentials' => ['token' => 'secret_notion'],
    ]);
}

it('declares the notion catalog', function () {
    $keys = collect((new NotionConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('search.query', 'pages.get', 'pages.create', 'blocks.append', 'databases.query')
        ->and((new NotionConnector)->group())->toBe('Contenido');
});

it('searches with the required headers', function () {
    Http::fake(['api.notion.com/v1/search' => Http::response(['results' => [['id' => 'p1']]], 200)]);

    $result = (new NotionConnector)->execute(notion(), 'search.query', ['query' => 'notas']);

    expect($result->ok)->toBeTrue()->and($result->data['results'][0]['id'])->toBe('p1');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret_notion')
        && $request->hasHeader('Notion-Version', '2022-06-28')
        && $request->data()['query'] === 'notas');
});

it('creates a page under a database', function () {
    Http::fake(['api.notion.com/v1/pages' => Http::response(['id' => 'new-page'], 200)]);

    $result = (new NotionConnector)->execute(notion(), 'pages.create', [
        'parent_type' => 'database',
        'parent_id' => 'db1',
        'title' => 'Mi página',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['id'])->toBe('new-page');

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['parent']['database_id'] === 'db1'
        && $request->data()['properties']['title']['title'][0]['text']['content'] === 'Mi página');
});

it('tests the connection via users.me', function () {
    Http::fake(['api.notion.com/v1/users/me' => Http::response(['name' => 'Mega'], 200)]);

    $result = (new NotionConnector)->test(notion());

    expect($result->ok)->toBeTrue()->and($result->meta['name'])->toBe('Mega');
});
