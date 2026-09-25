<?php

use App\Integrations\Connectors\Arr\QbittorrentConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function qbittorrent(): Connection
{
    return Connection::factory()->make([
        'kind' => 'qbittorrent',
        'base_url' => 'http://qb.local',
        'auth_type' => 'basic',
        'credentials' => ['username' => 'admin', 'password' => 'secret'],
    ]);
}

it('declares the qbittorrent catalog', function () {
    $keys = collect((new QbittorrentConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('torrents.list', 'torrents.add', 'torrents.pause', 'torrents.delete', 'transfer.info')
        ->and((new QbittorrentConnector)->defaultBaseUrl())->toBe('http://localhost:8080');
});

it('logs in and lists torrents with the session cookie', function () {
    Http::fake([
        'qb.local/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=abc123; path=/']),
        'qb.local/api/v2/torrents/info*' => Http::response([['hash' => 'h1', 'name' => 'Ubuntu']], 200),
    ]);

    $result = (new QbittorrentConnector)->execute(qbittorrent(), 'torrents.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['name'])->toBe('Ubuntu');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'torrents/info')
        && $request->hasHeader('Cookie', 'SID=abc123'));
});

it('adds a torrent as form data', function () {
    Http::fake([
        'qb.local/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=abc123; path=/']),
        'qb.local/api/v2/torrents/add' => Http::response('Ok.', 200),
    ]);

    $result = (new QbittorrentConnector)->execute(qbittorrent(), 'torrents.add', [
        'urls' => 'magnet:?xt=urn:btih:abc',
        'paused' => true,
    ]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'torrents/add')
        && $request->data()['urls'] === 'magnet:?xt=urn:btih:abc'
        && $request->data()['paused'] === 'true'
        && $request->hasHeader('Cookie', 'SID=abc123'));
});

it('deletes torrents with files', function () {
    Http::fake([
        'qb.local/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=abc123; path=/']),
        'qb.local/api/v2/torrents/delete' => Http::response('', 200),
    ]);

    $result = (new QbittorrentConnector)->execute(qbittorrent(), 'torrents.delete', [
        'hashes' => 'h1|h2',
        'delete_files' => true,
    ]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'torrents/delete')
        && ($request->data()['deleteFiles'] ?? null) === 'true');
});

it('tests the connection via app version', function () {
    Http::fake([
        'qb.local/api/v2/auth/login' => Http::response('Ok.', 200, ['Set-Cookie' => 'SID=abc123; path=/']),
        'qb.local/api/v2/app/version' => Http::response('v5.0.0', 200),
    ]);

    $result = (new QbittorrentConnector)->test(qbittorrent());

    expect($result->ok)->toBeTrue()->and($result->meta['version'])->toBe('v5.0.0');
});
