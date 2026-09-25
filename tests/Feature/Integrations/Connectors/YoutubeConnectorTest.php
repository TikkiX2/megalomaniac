<?php

use App\Integrations\Connectors\Youtube\YoutubeConnector;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function youtube(): Connection
{
    return Connection::factory()->make([
        'kind' => 'youtube',
        'base_url' => 'https://www.googleapis.com',
        'credentials' => ['api_key' => 'yt_key'],
    ]);
}

it('declares the youtube catalog', function () {
    $keys = collect((new YoutubeConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('videos.search', 'videos.get', 'channels.get', 'playlist.items', 'videos.categories')
        ->and((new YoutubeConnector)->group())->toBe('Contenido');
});

it('searches videos with the api key', function () {
    Http::fake(['www.googleapis.com/youtube/v3/search*' => Http::response(['items' => [['id' => ['videoId' => 'v1']]]], 200)]);

    $result = (new YoutubeConnector)->execute(youtube(), 'videos.search', ['query' => 'laravel']);

    expect($result->ok)->toBeTrue()->and($result->data['items'][0]['id']['videoId'])->toBe('v1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'key=yt_key')
        && str_contains($request->url(), 'q=laravel')
        && str_contains($request->url(), 'type=video'));
});

it('lists playlist items', function () {
    Http::fake(['www.googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [['snippet' => ['title' => 'V']]]], 200)]);

    $result = (new YoutubeConnector)->execute(youtube(), 'playlist.items', ['playlist_id' => 'PL123']);

    expect($result->ok)->toBeTrue()->and($result->data['items'][0]['snippet']['title'])->toBe('V');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'playlistId=PL123'));
});

it('gets a channel by handle', function () {
    Http::fake(['www.googleapis.com/youtube/v3/channels*' => Http::response(['items' => [['id' => 'UC1']]], 200)]);

    $result = (new YoutubeConnector)->execute(youtube(), 'channels.get', ['for_handle' => '@laravelphp']);

    expect($result->ok)->toBeTrue()->and($result->data['items'][0]['id'])->toBe('UC1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'forHandle=%40laravelphp')
        || str_contains($request->url(), 'forHandle=@laravelphp'));
});

it('tests the connection via categories', function () {
    Http::fake(['www.googleapis.com/youtube/v3/videoCategories*' => Http::response(['items' => []], 200)]);

    $result = (new YoutubeConnector)->test(youtube());

    expect($result->ok)->toBeTrue();
});
