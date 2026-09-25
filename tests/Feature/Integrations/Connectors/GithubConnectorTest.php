<?php

use App\Integrations\Connectors\Github\GithubConnector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function github(): Connection
{
    return Connection::factory()->make([
        'kind' => 'github',
        'base_url' => 'https://api.github.com',
        'credentials' => ['token' => 'ghp_test'],
    ]);
}

it('declares the github catalog', function () {
    $connector = new GithubConnector;
    $keys = collect($connector->actions())->pluck('key')->all();

    expect($keys)->toContain(
        'repos.list', 'repos.get', 'issues.list', 'issues.get', 'issues.create',
        'issues.comment', 'issues.close', 'pulls.list', 'pulls.get', 'pulls.files',
        'pulls.create', 'actions.runs.list', 'actions.runs.rerun',
        'notifications.list', 'search.code', 'search.issues',
    )
        ->and($connector->authFields()[0]->name)->toBe('token')
        ->and($connector->group())->toBe('Dev')
        ->and(collect($connector->actions())->firstWhere('key', 'issues.create')->access)->toBe(ActionAccess::Write);
});

it('lists repositories', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response([['full_name' => 'a/b']], 200)]);

    $result = (new GithubConnector)->execute(github(), 'repos.list', ['per_page' => 5]);

    expect($result->ok)->toBeTrue()->and($result->data[0]['full_name'])->toBe('a/b');
});

it('creates an issue', function () {
    Http::fake(['api.github.com/repos/a/b/issues' => Http::response(['number' => 7, 'title' => 'Bug'], 201)]);

    $result = (new GithubConnector)->execute(github(), 'issues.create', [
        'owner' => 'a',
        'repo' => 'b',
        'title' => 'Bug',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['number'])->toBe(7);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request->data()['title'] === 'Bug'
        && $request->url() === 'https://api.github.com/repos/a/b/issues');
});

it('maps github errors', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $result = (new GithubConnector)->execute(github(), 'repos.get', ['owner' => 'a', 'repo' => 'missing']);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('404');
});

it('tests the connection against the authenticated user endpoint', function () {
    Http::fake(['api.github.com/user' => Http::response(['login' => 'me'], 200)]);

    $result = (new GithubConnector)->test(github());

    expect($result->ok)->toBeTrue()->and($result->meta['login'])->toBe('me');
});

it('falls back to the default github base url when none is configured', function () {
    Http::fake(['api.github.com/user' => Http::response(['login' => 'me'], 200)]);

    $connection = Connection::factory()->make([
        'kind' => 'github',
        'base_url' => null,
        'credentials' => ['token' => 'ghp_test'],
    ]);

    $result = (new GithubConnector)->test($connection);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/user');
});

it('rejects unknown actions', function () {
    $result = (new GithubConnector)->execute(github(), 'nope.nope', []);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('nope.nope');
});
